<?php

declare(strict_types=1);

namespace App\Module\Core\Exception;

final class NotFoundException extends DomainException
{
    public function __construct(string $message)
    {
        parent::__construct($message, 404);
    }
}
