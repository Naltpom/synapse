<?php

declare(strict_types=1);

namespace App\Module\Staffing\Controller;

use App\Module\Staffing\Entity\Consultant;
use App\Module\Staffing\Entity\Mission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/staffing')]
final class StaffingController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/consultants', name: 'staffing_consultants_list', methods: ['GET'])]
    public function consultants(): JsonResponse
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

        return $this->json(array_map(static function (Consultant $c) use ($today): array {
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
        }, $consultants));
    }

    #[Route('/missions', name: 'staffing_missions_list', methods: ['GET'])]
    public function missions(): JsonResponse
    {
        /** @var list<Mission> $missions */
        $missions = $this->em->createQueryBuilder()
            ->select('m', 'a', 'c')
            ->from(Mission::class, 'm')
            ->leftJoin('m.assignments', 'a')
            ->leftJoin('a.consultant', 'c')
            ->orderBy('m.startDate', 'DESC')
            ->getQuery()->getResult();

        return $this->json(array_map(static fn (Mission $m): array => $m->toArray(), $missions));
    }

    #[Route('/missions/{id}', name: 'staffing_missions_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function mission(int $id): JsonResponse
    {
        $mission = $this->em->find(Mission::class, $id);
        if (null === $mission) {
            return $this->json(['error' => 'Mission introuvable.'], 404);
        }

        return $this->json($mission->toArray(withAssignments: true));
    }
}
