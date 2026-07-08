<?php

declare(strict_types=1);

namespace App\Module\Project\Enum;

enum ProjectStatus: string
{
    case Cadrage = 'cadrage';
    case EnCours = 'en_cours';
    case Recette = 'recette';
    case Clos = 'clos';
}
