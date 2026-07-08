<?php

declare(strict_types=1);

namespace App\Module\Billing\Enum;

enum InvoiceStatus: string
{
    case Brouillon = 'brouillon';
    case Envoyee = 'envoyee';
    case Payee = 'payee';
    case EnRetard = 'en_retard';
}
