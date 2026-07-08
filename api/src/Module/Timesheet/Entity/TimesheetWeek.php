<?php

declare(strict_types=1);

namespace App\Module\Timesheet\Entity;

use App\Module\Timesheet\Enum\WeekStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Le statut d'une semaine de CRA : brouillon → soumise → validée.
 * Tant qu'aucune ligne n'existe, la semaine est implicitement en brouillon.
 */
#[ORM\Entity]
#[ORM\Table(name: 'timesheet_week')]
#[ORM\UniqueConstraint(name: 'uniq_week', columns: ['consultant_id', 'week_start'])]
class TimesheetWeek
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $consultantId;

    #[ORM\Column(length: 160)]
    private string $consultantName;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $weekStart;

    #[ORM\Column(length: 12, enumType: WeekStatus::class)]
    private WeekStatus $status;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $submittedAt = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $decidedBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $decidedAt = null;

    public function __construct(int $consultantId, string $consultantName, \DateTimeImmutable $weekStart)
    {
        $this->consultantId = $consultantId;
        $this->consultantName = $consultantName;
        $this->weekStart = $weekStart;
        $this->status = WeekStatus::Draft;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConsultantId(): int
    {
        return $this->consultantId;
    }

    public function getStatus(): WeekStatus
    {
        return $this->status;
    }

    public function submit(): self
    {
        $this->status = WeekStatus::Submitted;
        $this->submittedAt = new \DateTimeImmutable();

        return $this;
    }

    public function validate(string $decidedBy): self
    {
        $this->status = WeekStatus::Validated;
        $this->decidedBy = $decidedBy;
        $this->decidedAt = new \DateTimeImmutable();

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'consultantId' => $this->consultantId,
            'consultantName' => $this->consultantName,
            'weekStart' => $this->weekStart->format('Y-m-d'),
            'status' => $this->status->value,
            'submittedAt' => $this->submittedAt?->format(\DateTimeInterface::ATOM),
            'decidedBy' => $this->decidedBy,
            'decidedAt' => $this->decidedAt?->format(\DateTimeInterface::ATOM),
        ];
    }
}
