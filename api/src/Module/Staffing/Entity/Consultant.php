<?php

declare(strict_types=1);

namespace App\Module\Staffing\Entity;

use App\Module\Core\Enum\Practice;
use App\Module\Staffing\Enum\ConsultantGrade;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'staffing_consultant')]
class Consultant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    private string $firstName;

    #[ORM\Column(length: 80)]
    private string $lastName;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    #[ORM\Column(length: 40, enumType: Practice::class)]
    private Practice $practice;

    #[ORM\Column(length: 20, enumType: ConsultantGrade::class)]
    private ConsultantGrade $grade;

    /** Taux journalier moyen de vente, en euros. */
    #[ORM\Column]
    private int $dailyRate;

    /** Coût journalier chargé (salaire + charges), en euros — sert au calcul de marge. */
    #[ORM\Column(options: ['default' => 0])]
    private int $costRate = 0;

    /** @var list<string> */
    #[ORM\Column]
    private array $skills = [];

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $hiredAt;

    /** @var Collection<int, Assignment> */
    #[ORM\OneToMany(targetEntity: Assignment::class, mappedBy: 'consultant', cascade: ['persist', 'remove'])]
    private Collection $assignments;

    /** @param list<string> $skills */
    public function __construct(
        string $firstName,
        string $lastName,
        string $email,
        Practice $practice,
        ConsultantGrade $grade,
        int $dailyRate,
        array $skills,
        \DateTimeImmutable $hiredAt,
    ) {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = $email;
        $this->practice = $practice;
        $this->grade = $grade;
        $this->dailyRate = $dailyRate;
        $this->skills = $skills;
        $this->hiredAt = $hiredAt;
        $this->assignments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFullName(): string
    {
        return $this->firstName.' '.$this->lastName;
    }

    public function getPractice(): Practice
    {
        return $this->practice;
    }

    public function getCostRate(): int
    {
        return $this->costRate;
    }

    public function setCostRate(int $costRate): self
    {
        $this->costRate = $costRate;

        return $this;
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

    /** Charge cumulée (%) sur les affectations actives à une date donnée, plafonnée à 100. */
    public function allocationAt(\DateTimeImmutable $date): int
    {
        $total = 0;
        foreach ($this->assignments as $assignment) {
            if ($assignment->isActiveAt($date)) {
                $total += $assignment->getAllocation();
            }
        }

        return min($total, 100);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $today = new \DateTimeImmutable('today');

        return [
            'id' => $this->id,
            'fullName' => $this->getFullName(),
            'email' => $this->email,
            'practice' => $this->practice->value,
            'practiceLabel' => $this->practice->label(),
            'grade' => $this->grade->value,
            'dailyRate' => $this->dailyRate,
            'skills' => $this->skills,
            'hiredAt' => $this->hiredAt->format('Y-m-d'),
            'allocation' => $this->allocationAt($today),
        ];
    }
}
