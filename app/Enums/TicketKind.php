<?php

declare(strict_types=1);

namespace App\Enums;

enum TicketKind: string
{
    case Free = 'free';
    case Paid = 'paid';
    case Donation = 'donation';
    case Group = 'group';
    case Vip = 'vip';
    case EarlyBird = 'early_bird';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Free',
            self::Paid => 'Paid',
            self::Donation => 'Donation',
            self::Group => 'Group',
            self::Vip => 'VIP',
            self::EarlyBird => 'Early bird',
        };
    }
}
