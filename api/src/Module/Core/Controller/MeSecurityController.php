<?php

declare(strict_types=1);

namespace App\Module\Core\Controller;

use App\Module\Core\Entity\AuditLog;
use App\Module\Core\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Hygiène de compte : chaque utilisateur voit son propre historique de connexion
 * (extrait du journal d'audit filtré sur son identité — jamais celui des autres).
 */
final class MeSecurityController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/api/me/security', name: 'api_me_security', methods: ['GET'])]
    public function security(#[CurrentUser] User $user): JsonResponse
    {
        /** @var list<AuditLog> $entries */
        $entries = $this->em->createQueryBuilder()
            ->select('a')
            ->from(AuditLog::class, 'a')
            ->where('a.actor = :actor')
            ->andWhere('a.action IN (:actions)')
            ->setParameter('actor', $user->getUserIdentifier())
            ->setParameter('actions', ['login', 'login_failure'])
            ->orderBy('a.occurredAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()->getResult();

        return $this->json([
            'session' => [
                'email' => $user->getEmail(),
                'fullName' => $user->getFullName(),
                'jobTitle' => $user->getJobTitle(),
                'roles' => $user->getRoles(),
            ],
            'logins' => array_map(static fn (AuditLog $a): array => $a->toArray(), $entries),
        ]);
    }
}
