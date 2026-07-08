<?php

declare(strict_types=1);

namespace App\Module\Project\Entity;

use App\Module\Project\Enum\ProjectHealth;
use App\Module\Project\Enum\ProjectStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Suivi de delivery d'une mission : jalons, avancement, météo projet.
 * Référence la mission par identifiant (frontière de module, pas de relation Doctrine).
 */
#[ORM\Entity]
#[ORM\Table(name: 'project_project')]
class Project
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $missionId;

    #[ORM\Column(length: 180)]
    private string $name;

    #[ORM\Column(length: 160)]
    private string $clientName;

    #[ORM\Column(length: 120)]
    private string $manager;

    /** Avancement en pourcentage. */
    #[ORM\Column]
    private int $progress;

    #[ORM\Column(length: 10, enumType: ProjectHealth::class)]
    private ProjectHealth $health;

    #[ORM\Column(length: 180)]
    private string $nextMilestone;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $dueDate;

    #[ORM\Column(length: 20, enumType: ProjectStatus::class)]
    private ProjectStatus $status;

    public function __construct(
        ?int $missionId,
        string $name,
        string $clientName,
        string $manager,
        int $progress,
        ProjectHealth $health,
        string $nextMilestone,
        \DateTimeImmutable $dueDate,
        ProjectStatus $status,
    ) {
        $this->missionId = $missionId;
        $this->name = $name;
        $this->clientName = $clientName;
        $this->manager = $manager;
        $this->progress = $progress;
        $this->health = $health;
        $this->nextMilestone = $nextMilestone;
        $this->dueDate = $dueDate;
        $this->status = $status;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatus(): ProjectStatus
    {
        return $this->status;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'missionId' => $this->missionId,
            'name' => $this->name,
            'clientName' => $this->clientName,
            'manager' => $this->manager,
            'progress' => $this->progress,
            'health' => $this->health->value,
            'nextMilestone' => $this->nextMilestone,
            'dueDate' => $this->dueDate->format('Y-m-d'),
            'status' => $this->status->value,
        ];
    }
}
