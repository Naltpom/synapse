<?php

declare(strict_types=1);

namespace App\Module\Crm\Enum;

enum ClientStatus: string
{
    case Prospect = 'prospect';
    case Actif = 'actif';
    case Inactif = 'inactif';
}
