<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case None = 'none';
    case Pending = 'pending';
    case Approved = 'approved';
    case Declined = 'declined';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';
}
