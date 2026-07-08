<?php

declare(strict_types=1);

namespace App\Module\Core\Service;

use App\Module\Billing\Entity\Invoice;
use App\Module\Billing\Enum\InvoiceStatus;
use App\Module\Hr\Entity\LeaveRequest;
use App\Module\Hr\Enum\LeaveStatus;
use App\Module\Staffing\Entity\Consultant;
use App\Module\Timesheet\Entity\TimesheetWeek;
use App\Module\Timesheet\Enum\WeekStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Compteurs des badges de navigation — lecture transverse légère,
 * même règle que le dashboard : jamais d'écriture hors du module d'origine.
 */
final class NavCounterService
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** @return array{staffingBench: int, billingOverdue: int, hrPending: int, craPending: int} */
    public function counters(): array
    {
        return [
            'staffingBench' => $this->benchCount(),
            'billingOverdue' => $this->countByStatus(Invoice::class, InvoiceStatus::EnRetard),
            'hrPending' => $this->countByStatus(LeaveRequest::class, LeaveStatus::PendingApproval),
            'craPending' => $this->countByStatus(TimesheetWeek::class, WeekStatus::Submitted),
        ];
    }

    private function benchCount(): int
    {
        /** @var list<Consultant> $consultants */
        $consultants = $this->em->createQueryBuilder()
            ->select('c', 'a')
            ->from(Consultant::class, 'c')
            ->leftJoin('c.assignments', 'a')
            ->getQuery()->getResult();

        $today = new \DateTimeImmutable('today');

        return count(array_filter($consultants, static fn (Consultant $c): bool => 0 === $c->allocationAt($today)));
    }

    /** @param class-string $entity */
    private function countByStatus(string $entity, \BackedEnum $status): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(e.id)')
            ->from($entity, 'e')
            ->where('e.status = :status')
            ->setParameter('status', $status)
            ->getQuery()->getSingleScalarResult();
    }
}
