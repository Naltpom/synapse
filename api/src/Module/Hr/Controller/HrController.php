<?php

declare(strict_types=1);

namespace App\Module\Hr\Controller;

use App\Module\Hr\Entity\LeaveRequest;
use App\Module\Hr\Enum\LeaveStatus;
use App\Module\Hr\Enum\LeaveType;
use App\Module\Staffing\Entity\Consultant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/hr')]
final class HrController extends AbstractController
{
    private const CALENDAR_COLUMNS = 10;
    private const CALENDAR_ROWS = 8;

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/leaves', name: 'hr_leaves_list', methods: ['GET'])]
    public function leaves(Request $request): JsonResponse
    {
        $qb = $this->em->createQueryBuilder()
            ->select('l')
            ->from(LeaveRequest::class, 'l')
            ->orderBy('l.createdAt', 'DESC');

        $status = LeaveStatus::tryFrom((string) $request->query->get('status', ''));
        if (null !== $status) {
            $qb->where('l.status = :status')->setParameter('status', $status);
        }

        /** @var list<LeaveRequest> $leaves */
        $leaves = $qb->getQuery()->getResult();

        return $this->json(array_map(static fn (LeaveRequest $l): array => $l->toArray(), $leaves));
    }

    #[Route('/leaves', name: 'hr_leaves_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        // Sans lien User↔Consultant, poser un congé « pour le compte de » un consultant
        // arbitraire est une fonction back-office réservée aux managers (même règle que le CRA).
        if (!$this->isGranted('ROLE_MANAGER') && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'La saisie des congés est réservée aux managers.'], 403);
        }

        $payload = $request->toArray();

        $consultantId = (int) ($payload['consultantId'] ?? 0);
        $type = LeaveType::tryFrom((string) ($payload['type'] ?? ''));
        $startDate = $this->parseDate((string) ($payload['startDate'] ?? ''));
        $endDate = $this->parseDate((string) ($payload['endDate'] ?? ''));

        $errors = [];
        $consultant = $consultantId > 0 ? $this->em->find(Consultant::class, $consultantId) : null;
        if (null === $consultant) {
            $errors['consultantId'] = 'Consultant introuvable.';
        }
        if (null === $type) {
            $errors['type'] = 'Type invalide (conge_paye, rtt ou teletravail).';
        }
        if (null === $startDate) {
            $errors['startDate'] = 'Date de début invalide (AAAA-MM-JJ).';
        }
        if (null === $endDate) {
            $errors['endDate'] = 'Date de fin invalide (AAAA-MM-JJ).';
        }
        if (null !== $startDate && null !== $endDate && $endDate < $startDate) {
            $errors['endDate'] = 'La fin doit être postérieure ou égale au début.';
        }
        if ([] !== $errors) {
            return $this->json(['errors' => $errors], 422);
        }
        \assert(null !== $consultant && null !== $type && null !== $startDate && null !== $endDate);

        $days = isset($payload['days']) ? max(1, (int) $payload['days']) : $this->businessDays($startDate, $endDate);
        // La provenance n'est jamais dictée par le client : une saisie via l'API est 'app'.
        // La provenance 'mcp' n'est posée que par le backend assistant (ici, les fixtures).

        $leave = new LeaveRequest(
            $consultantId,
            $consultant->getFullName(),
            $type,
            $startDate,
            $endDate,
            $days,
            'app',
        );
        $this->em->persist($leave);
        $this->em->flush();

        return $this->json($leave->toArray(), 201);
    }

    #[Route('/leaves/{id}/approve', name: 'hr_leaves_approve', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function approve(int $id): JsonResponse
    {
        return $this->decide($id, approve: true);
    }

    #[Route('/leaves/{id}/reject', name: 'hr_leaves_reject', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reject(int $id): JsonResponse
    {
        return $this->decide($id, approve: false);
    }

    /**
     * Calendrier d'équipe : 10 colonnes de jours (week-ends suivants compressés,
     * comme la maquette), une ligne par consultant.
     */
    #[Route('/calendar', name: 'hr_calendar', methods: ['GET'])]
    public function calendar(): JsonResponse
    {
        $days = $this->calendarDays();

        // Pas de setMaxResults avec un fetch-join de collection (il limiterait les
        // lignes jointes, pas les consultants) : on tranche en PHP, volumétrie faible.
        /** @var list<Consultant> $allConsultants */
        $allConsultants = $this->em->createQueryBuilder()
            ->select('c', 'a', 'm')
            ->from(Consultant::class, 'c')
            ->leftJoin('c.assignments', 'a')
            ->leftJoin('a.mission', 'm')
            ->orderBy('c.id', 'ASC')
            ->getQuery()->getResult();
        $consultants = array_slice($allConsultants, 0, self::CALENDAR_ROWS);

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

        return $this->json([
            'days' => array_map(static fn (\DateTimeImmutable $d): array => [
                'date' => $d->format('Y-m-d'),
                'weekend' => (int) $d->format('N') >= 6,
            ], $days),
            'rows' => $rows,
        ]);
    }

    private function decide(int $id, bool $approve): JsonResponse
    {
        if (!$this->isGranted('ROLE_MANAGER') && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'La validation des congés est réservée aux managers.'], 403);
        }

        $leave = $this->em->find(LeaveRequest::class, $id);
        if (null === $leave) {
            return $this->json(['error' => 'Demande introuvable.'], 404);
        }
        if (LeaveStatus::PendingApproval !== $leave->getStatus()) {
            return $this->json(['error' => 'Cette demande a déjà été traitée.'], 409);
        }

        $decidedBy = $this->getUser()?->getUserIdentifier() ?? 'inconnu';
        $approve ? $leave->approve($decidedBy) : $leave->reject($decidedBy);
        $this->em->flush();

        return $this->json($leave->toArray());
    }

    /** @return list<\DateTimeImmutable> */
    private function calendarDays(): array
    {
        $days = [];
        $cursor = new \DateTimeImmutable('tomorrow');
        $firstWeekendSeen = false;
        $inFirstWeekend = false;

        while (count($days) < self::CALENDAR_COLUMNS) {
            $isWeekend = (int) $cursor->format('N') >= 6;
            if (!$isWeekend) {
                $days[] = $cursor;
                $inFirstWeekend = false;
            } elseif (!$firstWeekendSeen || $inFirstWeekend) {
                // Seul le premier week-end est affiché (colonnes grisées), les suivants sont sautés.
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

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return false === $date ? null : $date;
    }

    private function businessDays(\DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        $days = 0;
        for ($d = $start; $d <= $end; $d = $d->modify('+1 day')) {
            if ((int) $d->format('N') < 6) {
                ++$days;
            }
        }

        return max(1, $days);
    }
}
