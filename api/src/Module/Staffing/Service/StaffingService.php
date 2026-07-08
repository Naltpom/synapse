<?php

declare(strict_types=1);

namespace App\Module\Staffing\Service;

use App\Module\Core\Exception\NotFoundException;
use App\Module\Staffing\Entity\Consultant;
use App\Module\Staffing\Entity\Mission;
use Doctrine\ORM\EntityManagerInterface;

final class StaffingService
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** @return list<array<string, mixed>> */
    public function consultants(): array
    {
        /** @var list<Consultant> $consultants */
        $consultants = $this->em->createQueryBuilder()
            ->select('c', 'a', 'm')
            ->from(Consultant::class, 'c')
            ->leftJoin('c.assignments', 'a')
            ->leftJoin('a.mission', 'm')
            ->orderBy('c.lastName', 'ASC')
            ->getQuery()->getResult();

        $today = new \DateTimeImmutable('today');

        return array_map(static function (Consultant $c) use ($today): array {
            $data = $c->toArray();
            $data['activeMissions'] = [];
            foreach ($c->getAssignments() as $assignment) {
                if ($assignment->isActiveAt($today)) {
                    $data['activeMissions'][] = [
                        'missionId' => $assignment->getMission()->getId(),
                        'title' => $assignment->getMission()->getTitle(),
                        'clientName' => $assignment->getMission()->getClientName(),
                        'allocation' => $assignment->getAllocation(),
                    ];
                }
            }

            return $data;
        }, $consultants);
    }

    /** @return list<array<string, mixed>> */
    public function missions(): array
    {
        /** @var list<Mission> $missions */
        $missions = $this->em->createQueryBuilder()
            ->select('m', 'a', 'c')
            ->from(Mission::class, 'm')
            ->leftJoin('m.assignments', 'a')
            ->leftJoin('a.consultant', 'c')
            ->orderBy('m.startDate', 'DESC')
            ->getQuery()->getResult();

        return array_map(static fn (Mission $m): array => $m->toArray(), $missions);
    }

    /** @return array<string, mixed> */
    public function mission(int $id): array
    {
        $mission = $this->em->find(Mission::class, $id);
        if (null === $mission) {
            throw new NotFoundException('Mission introuvable.');
        }

        return $mission->toArray(withAssignments: true);
    }
}
