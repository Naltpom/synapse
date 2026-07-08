<?php

declare(strict_types=1);

namespace App\Module\Hr\Entity;

use App\Module\Hr\Enum\LeaveStatus;
use App\Module\Hr\Enum\LeaveType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Demande de congé. Le consultant est référencé par identifiant + nom dénormalisé
 * (frontière du monolithe modulaire, comme Mission et Invoice).
 * Le workflow impose une validation : pending_approval → approved|rejected.
 */
#[ORM\Entity]
#[ORM\Table(name: 'hr_leave_request')]
class LeaveRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $consultantId;

    #[ORM\Column(length: 160)]
    private string $consultantName;

    #[ORM\Column(length: 20, enumType: LeaveType::class)]
    private LeaveType $type;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $startDate;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $endDate;

    /** Jours ouvrés effectivement posés (peut être inférieur à l'intervalle). */
    #[ORM\Column]
    private int $days;

    #[ORM\Column(length: 20, enumType: LeaveStatus::class)]
    private LeaveStatus $status;

    /** Provenance de la demande : application ou assistant (mcp). */
    #[ORM\Column(length: 10)]
    private string $source;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $decidedBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $decidedAt = null;

    public function __construct(
        int $consultantId,
        string $consultantName,
        LeaveType $type,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        int $days,
        string $source = 'app',
        ?\DateTimeImmutable $createdAt = null,
    ) {
        $this->consultantId = $consultantId;
        $this->consultantName = $consultantName;
        $this->type = $type;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->days = $days;
        $this->status = LeaveStatus::PendingApproval;
        $this->source = $source;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConsultantId(): int
    {
        return $this->consultantId;
    }

    public function getStatus(): LeaveStatus
    {
        return $this->status;
    }

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function getEndDate(): \DateTimeImmutable
    {
        return $this->endDate;
    }

    public function covers(\DateTimeImmutable $day): bool
    {
        return $this->startDate <= $day && $day <= $this->endDate;
    }

    public function approve(string $decidedBy): self
    {
        $this->status = LeaveStatus::Approved;
        $this->decidedBy = $decidedBy;
        $this->decidedAt = new \DateTimeImmutable();

        return $this;
    }

    public function reject(string $decidedBy): self
    {
        $this->status = LeaveStatus::Rejected;
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
            'type' => $this->type->value,
            'typeLabel' => $this->type->label(),
            'startDate' => $this->startDate->format('Y-m-d'),
            'endDate' => $this->endDate->format('Y-m-d'),
            'days' => $this->days,
            'status' => $this->status->value,
            'source' => $this->source,
            'createdAt' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'decidedBy' => $this->decidedBy,
            'decidedAt' => $this->decidedAt?->format(\DateTimeInterface::ATOM),
        ];
    }
}
