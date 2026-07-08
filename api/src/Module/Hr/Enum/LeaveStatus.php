<?php

declare(strict_types=1);

namespace App\Module\Hr\Enum;

enum LeaveStatus: string
{
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
