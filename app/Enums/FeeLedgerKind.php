<?php

declare(strict_types=1);

namespace App\Enums;

enum FeeLedgerKind: string
{
    case Assessed = 'assessed';
    case Collected = 'collected';
    case Owed = 'owed';
    case Remitted = 'remitted';
}
