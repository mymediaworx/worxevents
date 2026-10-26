<?php

declare(strict_types=1);

namespace App\Enums;

enum RefundStatus: string
{
    case Requested = 'requested';
    case Submitted = 'submitted';
    case Completed = 'completed';
    case Failed = 'failed';
    case Rejected = 'rejected';
}
