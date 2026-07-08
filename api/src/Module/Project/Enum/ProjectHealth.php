<?php

declare(strict_types=1);

namespace App\Module\Project\Enum;

enum ProjectHealth: string
{
    case Vert = 'vert';
    case Orange = 'orange';
    case Rouge = 'rouge';
}
