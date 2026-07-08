<?php

declare(strict_types=1);

namespace App\Module\Timesheet\Entity;

use App\Module\Timesheet\Enum\TimeCategory;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une fraction de journée saisie au CRA (0,5 ou 1). Références inter-modules
 * par identifiants, comme partout dans le monolithe modulaire.
 */
#[ORM\Entity]
#[ORM\Table(name: 'timesheet_entry')]
#[ORM\UniqueConstraint(name: 'uniq_entry_line', columns: ['consultant_id', 'date', 'category', 'mission_id'])]
class TimeEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $consultantId;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $date;

    #[ORM\Column(length: 20, enumType: TimeCategory::class)]
    private TimeCategory $category;

    #[ORM\Column(nullable: true)]
    private ?int $missionId;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $missionTitle;

    /** 0.5 ou 1.0 */
    #[ORM\Column]
    private float $fraction;

    public function __construct(
        int $consultantId,
        \DateTimeImmutable $date,
        TimeCategory $category,
        ?int $missionId,
        ?string $missionTitle,
        float $fraction,
    ) {
        $this->consultantId = $consultantId;
        $this->date = $date;
        $this->category = $category;
        $this->missionId = $missionId;
        $this->missionTitle = $missionTitle;
        $this->fraction = $fraction;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConsultantId(): int
    {
        return $this->consultantId;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function getCategory(): TimeCategory
    {
        return $this->category;
    }

    public function getMissionId(): ?int
    {
        return $this->missionId;
    }

    public function getFraction(): float
    {
        return $this->fraction;
    }

    public function setFraction(float $fraction): self
    {
        $this->fraction = $fraction;

        return $this;
    }

    public function lineKey(): string
    {
        return TimeCategory::Mission === $this->category
            ? 'mission:'.$this->missionId
            : $this->category->value;
    }
}
