<?php

declare(strict_types=1);

namespace App\Enums;

enum TicketVisibility: string
{
    case Public = 'public';
    case Unlisted = 'unlisted';
    case Hidden = 'hidden';
}
