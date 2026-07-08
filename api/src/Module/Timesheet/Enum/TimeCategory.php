<?php

declare(strict_types=1);

namespace App\Module\Timesheet\Enum;

enum TimeCategory: string
{
    case Mission = 'mission';
    case Conge = 'conge';
    case Interne = 'interne';
    case AvantVente = 'avant_vente';

    public function label(): string
    {
        return match ($this) {
            self::Mission => 'Mission',
            self::Conge => 'Congé / absence',
            self::Interne => 'Interne / formation',
            self::AvantVente => 'Avant-vente',
        };
    }
}
