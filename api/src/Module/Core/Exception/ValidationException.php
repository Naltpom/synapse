<?php

declare(strict_types=1);

namespace App\Module\Core\Exception;

final class ValidationException extends DomainException
{
    /** @param array<string, string> $errors */
    public function __construct(array $errors, string $message = 'Données invalides.')
    {
        parent::__construct($message, 422, $errors);
    }
}
