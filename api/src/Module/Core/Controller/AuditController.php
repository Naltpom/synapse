<?php

declare(strict_types=1);

namespace App\Module\Core\Controller;

use App\Module\Core\Entity\AuditLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class AuditController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/api/audit', name: 'audit_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var list<AuditLog> $entries */
        $entries = $this->em->createQueryBuilder()
            ->select('a')
            ->from(AuditLog::class, 'a')
            ->orderBy('a.occurredAt', 'DESC')
            ->setMaxResults(100)
            ->getQuery()->getResult();

        return $this->json(array_map(static fn (AuditLog $a): array => $a->toArray(), $entries));
    }
}
