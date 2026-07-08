<?php

declare(strict_types=1);

namespace App\Module\Core\Service;

use App\Module\Core\Entity\AuditLog;
use App\Module\Core\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Hygiène de compte : l'utilisateur voit son propre historique de connexion,
 * extrait du journal d'audit filtré sur son identité — jamais celui des autres.
 */
final class SecurityProfileService
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** @return array<string, mixed> */
    public function profile(User $user): array
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

        return [
            'session' => [
                'email' => $user->getEmail(),
                'fullName' => $user->getFullName(),
                'jobTitle' => $user->getJobTitle(),
                'roles' => $user->getRoles(),
            ],
            'logins' => array_map(static fn (AuditLog $a): array => $a->toArray(), $entries),
        ];
    }
}
