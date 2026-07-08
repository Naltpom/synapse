<?php

declare(strict_types=1);

namespace App\Module\Hr\Service;

use App\Module\Hr\Entity\LeaveRequest;
use App\Module\Hr\Enum\LeaveStatus;
use App\Module\Staffing\Entity\Consultant;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Construit le calendrier d'équipe : 10 colonnes de jours (le premier week-end est
 * affiché grisé, les suivants sont sautés), une ligne par consultant.
 */
final class LeaveCalendarService
{
    private const COLUMNS = 10;
    private const ROWS = 8;

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** @return array{days: list<array{date: string, weekend: bool}>, rows: list<array{consultant: string, cells: list<array{state: string}>}>} */
    public function build(): array
    {
        $days = $this->days();

        // Pas de setMaxResults avec un fetch-join de collection (il limiterait les lignes
        // jointes, pas les consultants) : on tranche en PHP, volumétrie faible.
        /** @var list<Consultant> $allConsultants */
        $allConsultants = $this->em->createQueryBuilder()
            ->select('c', 'a', 'm')
            ->from(Consultant::class, 'c')
            ->leftJoin('c.assignments', 'a')
            ->leftJoin('a.mission', 'm')
            ->orderBy('c.id', 'ASC')
            ->getQuery()->getResult();
        $consultants = array_slice($allConsultants, 0, self::ROWS);

        /** @var list<LeaveRequest> $leaves */
        $leaves = $this->em->getRepository(LeaveRequest::class)->findAll();

        $rows = [];
        foreach ($consultants as $consultant) {
            $cells = [];
            foreach ($days as $day) {
                $cells[] = ['state' => $this->cellState($consultant, $leaves, $day)];
            }
            $rows[] = ['consultant' => $consultant->getFullName(), 'cells' => $cells];
        }

        return [
            'days' => array_map(static fn (\DateTimeImmutable $d): array => [
                'date' => $d->format('Y-m-d'),
                'weekend' => (int) $d->format('N') >= 6,
            ], $days),
            'rows' => $rows,
        ];
    }

    /** @return list<\DateTimeImmutable> */
    private function days(): array
    {
        $days = [];
        $cursor = new \DateTimeImmutable('tomorrow');
        $firstWeekendSeen = false;
        $inFirstWeekend = false;

        while (count($days) < self::COLUMNS) {
            $isWeekend = (int) $cursor->format('N') >= 6;
            if (!$isWeekend) {
                $days[] = $cursor;
                $inFirstWeekend = false;
            } elseif (!$firstWeekendSeen || $inFirstWeekend) {
                $days[] = $cursor;
                $firstWeekendSeen = true;
                $inFirstWeekend = true;
            }
            $cursor = $cursor->modify('+1 day');
        }

        return $days;
    }

    /** @param list<LeaveRequest> $leaves */
    private function cellState(Consultant $consultant, array $leaves, \DateTimeImmutable $day): string
    {
        if ((int) $day->format('N') >= 6) {
            return 'weekend';
        }

        foreach ($leaves as $leave) {
            if ($leave->getConsultantId() === $consultant->getId() && $leave->covers($day)) {
                if (LeaveStatus::Approved === $leave->getStatus()) {
                    return 'conge_valide';
                }
                if (LeaveStatus::PendingApproval === $leave->getStatus()) {
                    return 'conge_attente';
                }
            }
        }

        foreach ($consultant->getAssignments() as $assignment) {
            if ($assignment->isActiveAt($day)) {
                return 'mission';
            }
        }

        return 'dispo';
    }
}
