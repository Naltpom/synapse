<?php

declare(strict_types=1);

namespace App\Module\Core\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Journal d'audit immuable : qui a fait quoi, sur quoi, quand, depuis où.
 * Alimenté automatiquement par AuditSubscriber — aucun module n'écrit ici à la main.
 */
#[ORM\Entity]
#[ORM\Table(name: 'core_audit_log')]
#[ORM\Index(columns: ['occurred_at'], name: 'idx_audit_occurred_at')]
class AuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $occurredAt;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $actor;

    #[ORM\Column(length: 40)]
    private string $action;

    #[ORM\Column(length: 120)]
    private string $subjectType;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $subjectId;

    /** @var array<string, mixed>|null */
    #[ORM\Column(nullable: true)]
    private ?array $changes;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ip;

    /** @param array<string, mixed>|null $changes */
    public function __construct(
        ?string $actor,
        string $action,
        string $subjectType,
        ?string $subjectId,
        ?array $changes = null,
        ?string $ip = null,
    ) {
        $this->occurredAt = new \DateTimeImmutable();
        $this->actor = $actor;
        $this->action = $action;
        $this->subjectType = $subjectType;
        $this->subjectId = $subjectId;
        $this->changes = $changes;
        $this->ip = $ip;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'occurredAt' => $this->occurredAt->format(\DateTimeInterface::ATOM),
            'actor' => $this->actor,
            'action' => $this->action,
            'subjectType' => $this->subjectType,
            'subjectId' => $this->subjectId,
            'changes' => $this->changes,
            'ip' => $this->ip,
        ];
    }
}
