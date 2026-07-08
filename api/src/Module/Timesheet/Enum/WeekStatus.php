<?php

declare(strict_types=1);

namespace App\Module\Timesheet\Enum;

enum WeekStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Validated = 'validated';
}
