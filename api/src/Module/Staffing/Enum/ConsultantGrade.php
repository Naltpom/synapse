<?php

declare(strict_types=1);

namespace App\Module\Staffing\Enum;

enum ConsultantGrade: string
{
    case Junior = 'junior';
    case Confirme = 'confirme';
    case Senior = 'senior';
    case Manager = 'manager';
}
