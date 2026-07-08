<?php

declare(strict_types=1);

namespace App\Module\Core\Service;

use App\Module\Core\Entity\AuditLog;
use Doctrine\ORM\EntityManagerInterface;

final class AuditService
{
    private const LIMIT = 100;

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** @return list<array<string, mixed>> */
    public function recent(): array
    {
        /** @var list<AuditLog> $entries */
        $entries = $this->em->createQueryBuilder()
            ->select('a')
            ->from(AuditLog::class, 'a')
            ->orderBy('a.occurredAt', 'DESC')
            ->setMaxResults(self::LIMIT)
            ->getQuery()->getResult();

        return array_map(static fn (AuditLog $a): array => $a->toArray(), $entries);
    }
}
