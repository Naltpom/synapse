<?php

declare(strict_types=1);

namespace App\Module\Timesheet\Controller;

use App\Module\Staffing\Entity\Consultant;
use App\Module\Timesheet\Entity\TimeEntry;
use App\Module\Timesheet\Entity\TimesheetWeek;
use App\Module\Timesheet\Enum\TimeCategory;
use App\Module\Timesheet\Enum\WeekStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/cra')]
final class TimesheetController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** La grille d'une semaine : lignes mission + catégories fixes, 5 jours ouvrés. */
    #[Route('', name: 'cra_grid', methods: ['GET'])]
    public function grid(Request $request): JsonResponse
    {
        $consultant = $this->em->find(Consultant::class, (int) $request->query->get('consultantId', 0));
        if (null === $consultant) {
            return $this->json(['error' => 'Consultant introuvable.'], 404);
        }
        $weekStart = $this->mondayOf((string) $request->query->get('week', 'today'));
        if (null === $weekStart) {
            return $this->json(['errors' => ['week' => 'Semaine invalide (AAAA-MM-JJ).']], 422);
        }

        $days = $this->weekDays($weekStart);
        $entries = $this->entriesFor($consultant->getId() ?? 0, $days);
        $week = $this->weekFor($consultant, $weekStart);

        // Lignes mission : les missions affectées au consultant qui chevauchent la semaine.
        $lines = [];
        $seenMissions = [];
        foreach ($consultant->getAssignments() as $assignment) {
            $mission = $assignment->getMission();
            $overlaps = false;
            foreach ($days as $day) {
                if ($assignment->isActiveAt($day)) {
                    $overlaps = true;
                    break;
                }
            }
            if (!$overlaps || isset($seenMissions[$mission->getId()])) {
                continue;
            }
            $seenMissions[$mission->getId()] = true;
            $lines[] = [
                'key' => 'mission:'.$mission->getId(),
                'label' => $mission->getTitle(),
                'sublabel' => $mission->getClientName(),
                'category' => TimeCategory::Mission->value,
                'missionId' => $mission->getId(),
            ];
        }
        foreach ([TimeCategory::Conge, TimeCategory::Interne, TimeCategory::AvantVente] as $category) {
            $lines[] = [
                'key' => $category->value,
                'label' => $category->label(),
                'sublabel' => null,
                'category' => $category->value,
                'missionId' => null,
            ];
        }

        $byLine = [];
        foreach ($entries as $entry) {
            $byLine[$entry->lineKey()][$entry->getDate()->format('Y-m-d')] = $entry->getFraction();
        }

        $grid = array_map(static function (array $line) use ($byLine, $days): array {
            $line['cells'] = array_map(static fn (\DateTimeImmutable $day): array => [
                'date' => $day->format('Y-m-d'),
                'fraction' => $byLine[$line['key']][$day->format('Y-m-d')] ?? 0,
            ], $days);

            return $line;
        }, $lines);

        return $this->json([
            'consultant' => ['id' => $consultant->getId(), 'name' => $consultant->getFullName()],
            'week' => $week?->toArray() ?? [
                'consultantId' => $consultant->getId(),
                'weekStart' => $weekStart->format('Y-m-d'),
                'status' => WeekStatus::Draft->value,
            ],
            'days' => array_map(static fn (\DateTimeImmutable $d): string => $d->format('Y-m-d'), $days),
            'lines' => $grid,
        ]);
    }

    /** Saisie d'une cellule : 0 efface, 0.5 ou 1 crée/écrase. Total journalier plafonné à 1. */
    #[Route('/entries', name: 'cra_entry_upsert', methods: ['PUT'])]
    public function upsert(Request $request): JsonResponse
    {
        $payload = $request->toArray();

        $consultant = $this->em->find(Consultant::class, (int) ($payload['consultantId'] ?? 0));
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($payload['date'] ?? '')) ?: null;
        $fraction = (float) ($payload['fraction'] ?? -1);
        $lineKey = (string) ($payload['lineKey'] ?? '');

        $errors = [];
        if (null === $consultant) {
            $errors['consultantId'] = 'Consultant introuvable.';
        }
        if (null === $date) {
            $errors['date'] = 'Date invalide (AAAA-MM-JJ).';
        } elseif ((int) $date->format('N') >= 6) {
            $errors['date'] = 'Pas de saisie le week-end.';
        }
        if (!in_array($fraction, [0.0, 0.5, 1.0], true)) {
            $errors['fraction'] = 'Fraction autorisée : 0, 0.5 ou 1.';
        }
        [$category, $missionId, $missionTitle] = $this->resolveLine($lineKey);
        if (null === $category) {
            $errors['lineKey'] = 'Ligne inconnue.';
        }
        if ([] !== $errors) {
            return $this->json(['errors' => $errors], 422);
        }
        \assert(null !== $consultant && null !== $date && null !== $category);

        $week = $this->weekFor($consultant, $this->mondayOf($date->format('Y-m-d')) ?? $date);
        if (null !== $week && WeekStatus::Draft !== $week->getStatus()) {
            return $this->json(['error' => 'Cette semaine est soumise : plus de saisie possible.'], 409);
        }

        $entries = $this->entriesFor($consultant->getId() ?? 0, [$date]);
        $existing = null;
        $dayTotal = 0.0;
        foreach ($entries as $entry) {
            if ($entry->lineKey() === $lineKey) {
                $existing = $entry;
            } else {
                $dayTotal += $entry->getFraction();
            }
        }
        if ($dayTotal + $fraction > 1.0) {
            return $this->json(['errors' => ['fraction' => 'Le total du jour dépasserait une journée.']], 422);
        }

        if (0.0 === $fraction) {
            if (null !== $existing) {
                $this->em->remove($existing);
            }
        } elseif (null !== $existing) {
            $existing->setFraction($fraction);
        } else {
            $this->em->persist(new TimeEntry($consultant->getId() ?? 0, $date, $category, $missionId, $missionTitle, $fraction));
        }
        $this->em->flush();

        return $this->json(['ok' => true]);
    }

    #[Route('/submit', name: 'cra_submit', methods: ['POST'])]
    public function submit(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $consultant = $this->em->find(Consultant::class, (int) ($payload['consultantId'] ?? 0));
        $weekStart = $this->mondayOf((string) ($payload['week'] ?? ''));
        if (null === $consultant || null === $weekStart) {
            return $this->json(['error' => 'Consultant ou semaine invalide.'], 422);
        }

        // Une semaine sans aucun temps saisi ne peut pas être soumise (invariant du brouillon).
        if ([] === $this->entriesFor($consultant->getId() ?? 0, $this->weekDays($weekStart))) {
            return $this->json(['error' => 'Aucun temps saisi : rien à soumettre.'], 422);
        }

        $week = $this->weekFor($consultant, $weekStart);
        if (null === $week) {
            $week = new TimesheetWeek($consultant->getId() ?? 0, $consultant->getFullName(), $weekStart);
            $this->em->persist($week);
        }
        if (WeekStatus::Draft !== $week->getStatus()) {
            return $this->json(['error' => 'Cette semaine a déjà été soumise.'], 409);
        }

        $week->submit();
        $this->em->flush();

        return $this->json($week->toArray());
    }

    #[Route('/validate', name: 'cra_validate', methods: ['POST'])]
    public function validate(Request $request): JsonResponse
    {
        if (!$this->isGranted('ROLE_MANAGER') && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'La validation des CRA est réservée aux managers.'], 403);
        }

        $payload = $request->toArray();
        $consultant = $this->em->find(Consultant::class, (int) ($payload['consultantId'] ?? 0));
        $weekStart = $this->mondayOf((string) ($payload['week'] ?? ''));
        if (null === $consultant || null === $weekStart) {
            return $this->json(['error' => 'Consultant ou semaine invalide.'], 422);
        }

        $week = $this->weekFor($consultant, $weekStart);
        if (null === $week || WeekStatus::Submitted !== $week->getStatus()) {
            return $this->json(['error' => 'Seule une semaine soumise peut être validée.'], 409);
        }

        $week->validate($this->getUser()?->getUserIdentifier() ?? 'inconnu');
        $this->em->flush();

        return $this->json($week->toArray());
    }

    /** @return array{0: ?TimeCategory, 1: ?int, 2: ?string} */
    private function resolveLine(string $lineKey): array
    {
        if (str_starts_with($lineKey, 'mission:')) {
            $missionId = (int) substr($lineKey, 8);
            $mission = $this->em->find(\App\Module\Staffing\Entity\Mission::class, $missionId);

            return null === $mission ? [null, null, null] : [TimeCategory::Mission, $missionId, $mission->getTitle()];
        }

        $category = TimeCategory::tryFrom($lineKey);

        return null === $category || TimeCategory::Mission === $category
            ? [null, null, null]
            : [$category, null, null];
    }

    private function mondayOf(string $value): ?\DateTimeImmutable
    {
        $date = 'today' === $value
            ? new \DateTimeImmutable('today')
            : (\DateTimeImmutable::createFromFormat('!Y-m-d', $value) ?: null);

        return $date?->modify('monday this week');
    }

    /** @return list<\DateTimeImmutable> */
    private function weekDays(\DateTimeImmutable $monday): array
    {
        return array_map(static fn (int $i): \DateTimeImmutable => $monday->modify("+{$i} days"), range(0, 4));
    }

    /**
     * @param list<\DateTimeImmutable> $days
     *
     * @return list<TimeEntry>
     */
    private function entriesFor(int $consultantId, array $days): array
    {
        /** @var list<TimeEntry> $entries */
        $entries = $this->em->createQueryBuilder()
            ->select('e')
            ->from(TimeEntry::class, 'e')
            ->where('e.consultantId = :cid')->setParameter('cid', $consultantId)
            ->andWhere('e.date IN (:days)')
            ->setParameter('days', array_map(static fn (\DateTimeImmutable $d): string => $d->format('Y-m-d'), $days))
            ->getQuery()->getResult();

        return $entries;
    }

    private function weekFor(Consultant $consultant, \DateTimeImmutable $weekStart): ?TimesheetWeek
    {
        return $this->em->getRepository(TimesheetWeek::class)->findOneBy([
            'consultantId' => $consultant->getId(),
            'weekStart' => $weekStart,
        ]);
    }
}
