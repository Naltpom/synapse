<?php

declare(strict_types=1);

namespace App\Module\Hr\Enum;

enum LeaveType: string
{
    case CongePaye = 'conge_paye';
    case Rtt = 'rtt';
    case Teletravail = 'teletravail';

    public function label(): string
    {
        return match ($this) {
            self::CongePaye => 'Congé',
            self::Rtt => 'RTT',
            self::Teletravail => 'Télétravail exceptionnel',
        };
    }
}
