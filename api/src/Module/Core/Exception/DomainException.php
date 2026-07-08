<?php

declare(strict_types=1);

namespace App\Module\Core\Exception;

/**
 * Erreur métier portant son code HTTP et, éventuellement, un détail par champ.
 * Levée par la couche service ; traduite en JSON par ApiExceptionSubscriber —
 * les contrôleurs n'ont plus à gérer les cas d'erreur.
 */
abstract class DomainException extends \RuntimeException
{
    /** @param array<string, string> $errors */
    public function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly array $errors = [],
    ) {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }
}
