<?php

declare(strict_types=1);

namespace App\Module\Staffing\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'staffing_assignment')]
class Assignment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Mission::class, inversedBy: 'assignments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Mission $mission;

    #[ORM\ManyToOne(targetEntity: Consultant::class, inversedBy: 'assignments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Consultant $consultant;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $startDate;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $endDate;

    /** Pourcentage du temps du consultant alloué à la mission. */
    #[ORM\Column]
    private int $allocation;

    #[ORM\Column]
    private int $dailyRate;

    public function __construct(
        Mission $mission,
        Consultant $consultant,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        int $allocation,
        int $dailyRate,
    ) {
        $this->mission = $mission;
        $this->consultant = $consultant;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->allocation = $allocation;
        $this->dailyRate = $dailyRate;
        $mission->addAssignment($this);
        $consultant->addAssignment($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAllocation(): int
    {
        return $this->allocation;
    }

    public function getConsultant(): Consultant
    {
        return $this->consultant;
    }

    public function getMission(): Mission
    {
        return $this->mission;
    }

    public function isActiveAt(\DateTimeImmutable $date): bool
    {
        return $this->startDate <= $date && $date <= $this->endDate;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'consultantId' => $this->consultant->getId(),
            'consultantName' => $this->consultant->getFullName(),
            'missionId' => $this->mission->getId(),
            'missionTitle' => $this->mission->getTitle(),
            'clientName' => $this->mission->getClientName(),
            'startDate' => $this->startDate->format('Y-m-d'),
            'endDate' => $this->endDate->format('Y-m-d'),
            'allocation' => $this->allocation,
            'dailyRate' => $this->dailyRate,
        ];
    }
}
