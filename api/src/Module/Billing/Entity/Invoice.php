<?php

declare(strict_types=1);

namespace App\Module\Billing\Entity;

use App\Module\Billing\Enum\InvoiceStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Facture client. Références inter-modules par identifiants (clientId, missionId),
 * jamais par relation Doctrine — frontière du monolithe modulaire.
 */
#[ORM\Entity]
#[ORM\Table(name: 'billing_invoice')]
#[ORM\Index(columns: ['issued_at'], name: 'idx_invoice_issued_at')]
class Invoice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30, unique: true)]
    private string $number;

    #[ORM\Column]
    private int $clientId;

    #[ORM\Column(length: 160)]
    private string $clientName;

    #[ORM\Column(nullable: true)]
    private ?int $missionId;

    #[ORM\Column(length: 200)]
    private string $label;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $amountHt;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private string $vatRate;

    #[ORM\Column(length: 20, enumType: InvoiceStatus::class)]
    private InvoiceStatus $status;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $issuedAt;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $dueAt;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $paidAt;

    public function __construct(
        string $number,
        int $clientId,
        string $clientName,
        ?int $missionId,
        string $label,
        string $amountHt,
        string $vatRate,
        InvoiceStatus $status,
        \DateTimeImmutable $issuedAt,
        \DateTimeImmutable $dueAt,
        ?\DateTimeImmutable $paidAt = null,
    ) {
        $this->number = $number;
        $this->clientId = $clientId;
        $this->clientName = $clientName;
        $this->missionId = $missionId;
        $this->label = $label;
        $this->amountHt = $amountHt;
        $this->vatRate = $vatRate;
        $this->status = $status;
        $this->issuedAt = $issuedAt;
        $this->dueAt = $dueAt;
        $this->paidAt = $paidAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatus(): InvoiceStatus
    {
        return $this->status;
    }

    public function getAmountHt(): string
    {
        return $this->amountHt;
    }

    public function getIssuedAt(): \DateTimeImmutable
    {
        return $this->issuedAt;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'clientId' => $this->clientId,
            'clientName' => $this->clientName,
            'missionId' => $this->missionId,
            'label' => $this->label,
            'amountHt' => (float) $this->amountHt,
            'vatRate' => (float) $this->vatRate,
            'status' => $this->status->value,
            'issuedAt' => $this->issuedAt->format('Y-m-d'),
            'dueAt' => $this->dueAt->format('Y-m-d'),
            'paidAt' => $this->paidAt?->format('Y-m-d'),
        ];
    }
}
