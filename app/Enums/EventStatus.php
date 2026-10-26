<?php

declare(strict_types=1);

namespace App\Enums;

enum EventStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Paused = 'paused';
    case SoldOut = 'sold_out';
    case Canceled = 'canceled';
    case Completed = 'completed';
}
