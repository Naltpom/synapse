<?php

declare(strict_types=1);

namespace App\Module\Staffing\Entity;

use App\Module\Core\Enum\Practice;
use App\Module\Staffing\Enum\MissionStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Le client est référencé par identifiant + nom dénormalisé, jamais par relation Doctrine :
 * les modules communiquent par identifiants pour rester découplés (frontière du monolithe modulaire).
 */
#[ORM\Entity]
#[ORM\Table(name: 'staffing_mission')]
class Mission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $clientId;

    #[ORM\Column(length: 160)]
    private string $clientName;

    #[ORM\Column(length: 180)]
    private string $title;

    #[ORM\Column(length: 40, enumType: Practice::class)]
    private Practice $practice;

    #[ORM\Column(length: 20, enumType: MissionStatus::class)]
    private MissionStatus $status;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $startDate;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $endDate;

    #[ORM\Column]
    private int $budgetDays;

    /** @var Collection<int, Assignment> */
    #[ORM\OneToMany(targetEntity: Assignment::class, mappedBy: 'mission', cascade: ['persist', 'remove'])]
    private Collection $assignments;

    public function __construct(
        int $clientId,
        string $clientName,
        string $title,
        Practice $practice,
        MissionStatus $status,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        int $budgetDays,
    ) {
        $this->clientId = $clientId;
        $this->clientName = $clientName;
        $this->title = $title;
        $this->practice = $practice;
        $this->status = $status;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->budgetDays = $budgetDays;
        $this->assignments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getClientName(): string
    {
        return $this->clientName;
    }

    public function getStatus(): MissionStatus
    {
        return $this->status;
    }

    /** @return Collection<int, Assignment> */
    public function getAssignments(): Collection
    {
        return $this->assignments;
    }

    public function addAssignment(Assignment $assignment): self
    {
        if (!$this->assignments->contains($assignment)) {
            $this->assignments->add($assignment);
        }

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(bool $withAssignments = false): array
    {
        $data = [
            'id' => $this->id,
            'clientId' => $this->clientId,
            'clientName' => $this->clientName,
            'title' => $this->title,
            'practice' => $this->practice->value,
            'practiceLabel' => $this->practice->label(),
            'status' => $this->status->value,
            'startDate' => $this->startDate->format('Y-m-d'),
            'endDate' => $this->endDate->format('Y-m-d'),
            'budgetDays' => $this->budgetDays,
            'teamSize' => $this->assignments->count(),
        ];

        if ($withAssignments) {
            $data['assignments'] = array_map(
                static fn (Assignment $a): array => $a->toArray(),
                $this->assignments->toArray(),
            );
        }

        return $data;
    }
}
