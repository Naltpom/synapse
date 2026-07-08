<?php

declare(strict_types=1);

namespace App\Module\Staffing\Enum;

enum MissionStatus: string
{
    case AVenir = 'a_venir';
    case EnCours = 'en_cours';
    case Terminee = 'terminee';
}
