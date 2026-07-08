<?php

declare(strict_types=1);

namespace App\Module\Crm\Entity;

use App\Module\Core\Enum\Practice;
use App\Module\Crm\Enum\OpportunityStage;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'crm_opportunity')]
class Opportunity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'opportunities')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Client $client;

    #[ORM\Column(length: 180)]
    private string $title;

    #[ORM\Column(length: 40, enumType: Practice::class)]
    private Practice $practice;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $amount;

    #[ORM\Column(length: 20, enumType: OpportunityStage::class)]
    private OpportunityStage $stage;

    /** Probabilité de signature, en pourcentage. */
    #[ORM\Column]
    private int $probability;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $expectedCloseAt;

    #[ORM\Column(length: 120)]
    private string $owner;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        Client $client,
        string $title,
        Practice $practice,
        string $amount,
        OpportunityStage $stage,
        int $probability,
        \DateTimeImmutable $expectedCloseAt,
        string $owner,
    ) {
        $this->client = $client;
        $this->title = $title;
        $this->practice = $practice;
        $this->amount = $amount;
        $this->stage = $stage;
        $this->probability = $probability;
        $this->expectedCloseAt = $expectedCloseAt;
        $this->owner = $owner;
        $this->createdAt = new \DateTimeImmutable();
        $client->addOpportunity($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function getStage(): OpportunityStage
    {
        return $this->stage;
    }

    public function setStage(OpportunityStage $stage): self
    {
        $this->stage = $stage;
        $this->probability = match ($stage) {
            OpportunityStage::Gagnee => 100,
            OpportunityStage::Perdue => 0,
            default => $this->probability,
        };

        return $this;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function getProbability(): int
    {
        return $this->probability;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'clientId' => $this->client->getId(),
            'clientName' => $this->client->getName(),
            'title' => $this->title,
            'practice' => $this->practice->value,
            'practiceLabel' => $this->practice->label(),
            'amount' => (float) $this->amount,
            'stage' => $this->stage->value,
            'probability' => $this->probability,
            'expectedCloseAt' => $this->expectedCloseAt->format('Y-m-d'),
            'owner' => $this->owner,
        ];
    }
}
