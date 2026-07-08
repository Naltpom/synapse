<?php

declare(strict_types=1);

namespace App\Module\Timesheet\Service;

use App\Module\Core\Exception\ConflictException;
use App\Module\Core\Exception\NotFoundException;
use App\Module\Core\Exception\ValidationException;
use App\Module\Staffing\Entity\Consultant;
use App\Module\Staffing\Entity\Mission;
use App\Module\Timesheet\Entity\TimeEntry;
use App\Module\Timesheet\Entity\TimesheetWeek;
use App\Module\Timesheet\Enum\TimeCategory;
use App\Module\Timesheet\Enum\WeekStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class TimesheetService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
    ) {
    }

    /**
     * La grille d'une semaine : lignes mission (affectations chevauchant la semaine)
     * + catégories fixes, sur 5 jours ouvrés.
     *
     * @return array<string, mixed>
     */
    public function grid(int $consultantId, string $week): array
    {
        $consultant = $this->consultant($consultantId);
        $weekStart = $this->mondayOf($week);
        if (null === $weekStart) {
            throw new ValidationException(['week' => 'Semaine invalide (AAAA-MM-JJ).']);
        }

        $days = $this->weekDays($weekStart);
        $entries = $this->entriesFor($consultantId, $days);
        $existingWeek = $this->weekFor($consultant, $weekStart);

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

        return [
            'consultant' => ['id' => $consultant->getId(), 'name' => $consultant->getFullName()],
            'week' => $existingWeek?->toArray() ?? [
                'consultantId' => $consultant->getId(),
                'weekStart' => $weekStart->format('Y-m-d'),
                'status' => WeekStatus::Draft->value,
            ],
            'days' => array_map(static fn (\DateTimeImmutable $d): string => $d->format('Y-m-d'), $days),
            'lines' => $grid,
        ];
    }

    /**
     * Saisie d'une cellule : 0 efface, 0.5 ou 1 crée/écrase. Total journalier plafonné à 1.
     *
     * @param array<string, mixed> $payload
     */
    public function upsertEntry(array $payload): void
    {
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
            throw new ValidationException($errors);
        }
        \assert(null !== $consultant && null !== $date && null !== $category);

        $week = $this->weekFor($consultant, $this->mondayOf($date->format('Y-m-d')) ?? $date);
        if (null !== $week && WeekStatus::Draft !== $week->getStatus()) {
            throw new ConflictException('Cette semaine est soumise : plus de saisie possible.');
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
            throw new ValidationException(['fraction' => 'Le total du jour dépasserait une journée.']);
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
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function submit(array $payload): array
    {
        [$consultant, $weekStart] = $this->consultantAndWeek($payload);

        // Une semaine sans aucun temps saisi ne peut pas être soumise (invariant du brouillon).
        if ([] === $this->entriesFor($consultant->getId() ?? 0, $this->weekDays($weekStart))) {
            throw new ValidationException(['week' => 'Aucun temps saisi : rien à soumettre.']);
        }

        $week = $this->weekFor($consultant, $weekStart);
        if (null === $week) {
            $week = new TimesheetWeek($consultant->getId() ?? 0, $consultant->getFullName(), $weekStart);
            $this->em->persist($week);
        }
        if (WeekStatus::Draft !== $week->getStatus()) {
            throw new ConflictException('Cette semaine a déjà été soumise.');
        }

        $week->submit();
        $this->em->flush();

        return $week->toArray();
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function validateWeek(array $payload): array
    {
        [$consultant, $weekStart] = $this->consultantAndWeek($payload);

        $week = $this->weekFor($consultant, $weekStart);
        if (null === $week || WeekStatus::Submitted !== $week->getStatus()) {
            throw new ConflictException('Seule une semaine soumise peut être validée.');
        }

        $week->validate($this->security->getUser()?->getUserIdentifier() ?? 'inconnu');
        $this->em->flush();

        return $week->toArray();
    }

    private function consultant(int $id): Consultant
    {
        $consultant = $id > 0 ? $this->em->find(Consultant::class, $id) : null;
        if (null === $consultant) {
            throw new NotFoundException('Consultant introuvable.');
        }

        return $consultant;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{0: Consultant, 1: \DateTimeImmutable}
     */
    private function consultantAndWeek(array $payload): array
    {
        $consultant = $this->em->find(Consultant::class, (int) ($payload['consultantId'] ?? 0));
        $weekStart = $this->mondayOf((string) ($payload['week'] ?? ''));
        if (null === $consultant || null === $weekStart) {
            throw new ValidationException(['week' => 'Consultant ou semaine invalide.']);
        }

        return [$consultant, $weekStart];
    }

    /** @return array{0: ?TimeCategory, 1: ?int, 2: ?string} */
    private function resolveLine(string $lineKey): array
    {
        if (str_starts_with($lineKey, 'mission:')) {
            $missionId = (int) substr($lineKey, 8);
            $mission = $this->em->find(Mission::class, $missionId);

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
