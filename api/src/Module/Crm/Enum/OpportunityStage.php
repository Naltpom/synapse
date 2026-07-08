<?php

declare(strict_types=1);

namespace App\Module\Crm\Enum;

enum OpportunityStage: string
{
    case Qualification = 'qualification';
    case Proposition = 'proposition';
    case Negociation = 'negociation';
    case Gagnee = 'gagnee';
    case Perdue = 'perdue';

    public function isOpen(): bool
    {
        return !in_array($this, [self::Gagnee, self::Perdue], true);
    }
}
