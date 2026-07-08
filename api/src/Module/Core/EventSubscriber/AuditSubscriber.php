<?php

declare(strict_types=1);

namespace App\Module\Core\EventSubscriber;

use App\Module\Core\Entity\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Trace automatiquement chaque écriture (création, modification, suppression)
 * dans core_audit_log. Les champs sensibles sont masqués, les AuditLog eux-mêmes ignorés.
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
#[AsDoctrineListener(event: Events::postFlush)]
final class AuditSubscriber
{
    private const SENSITIVE_FIELDS = ['password'];

    /** @var list<AuditLog> */
    private array $pending = [];

    private bool $flushingAudit = false;

    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof AuditLog || $this->isCli()) {
            return;
        }

        $this->pending[] = $this->buildLog('create', $entity, null);
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof AuditLog || $this->isCli()) {
            return;
        }

        $changes = [];
        foreach ($args->getEntityChangeSet() as $field => $change) {
            if (in_array($field, self::SENSITIVE_FIELDS, true)) {
                $changes[$field] = '***';
                continue;
            }
            // Les champs de type collection ne sont pas des paires [avant, après].
            if (!is_array($change)) {
                continue;
            }
            $changes[$field] = ['from' => $this->normalize($change[0]), 'to' => $this->normalize($change[1])];
        }

        $this->pending[] = $this->buildLog('update', $entity, $changes);
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof AuditLog || $this->isCli()) {
            return;
        }

        $this->pending[] = $this->buildLog('delete', $entity, null);
    }

    /** Hors requête HTTP (fixtures, commandes console), on ne journalise pas. */
    private function isCli(): bool
    {
        return null === $this->requestStack->getCurrentRequest();
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->flushingAudit || [] === $this->pending) {
            return;
        }

        $em = $args->getObjectManager();
        \assert($em instanceof EntityManagerInterface);

        $this->flushingAudit = true;
        try {
            foreach ($this->pending as $log) {
                $em->persist($log);
            }
            $this->pending = [];
            $em->flush();
        } finally {
            $this->flushingAudit = false;
        }
    }

    /** @param array<string, mixed>|null $changes */
    private function buildLog(string $action, object $entity, ?array $changes): AuditLog
    {
        $subjectId = method_exists($entity, 'getId') ? (string) $entity->getId() : null;

        return new AuditLog(
            $this->security->getUser()?->getUserIdentifier(),
            $action,
            $this->shortClassName($entity),
            $subjectId,
            $changes,
            $this->requestStack->getCurrentRequest()?->getClientIp(),
        );
    }

    private function normalize(mixed $value): mixed
    {
        return match (true) {
            $value instanceof \DateTimeInterface => $value->format(\DateTimeInterface::ATOM),
            $value instanceof \BackedEnum => $value->value,
            is_scalar($value), null === $value => $value,
            default => get_debug_type($value),
        };
    }

    private function shortClassName(object $entity): string
    {
        $parts = explode('\\', $entity::class);

        return end($parts);
    }
}
