<?php

declare(strict_types=1);

namespace App\Domain\Fees;

use InvalidArgumentException;

/**
 * The booking fee is calculated once per order, after discounts, on ticket
 * subtotal only. Merchandise, donations, add-ons, and tax are recorded so the
 * snapshot can prove they were excluded.
 */
final class FeeBasis
{
    public function __construct(
        public readonly int $ticketSubtotalCents,
        public readonly int $discountCents = 0,
        public readonly int $merchandiseCents = 0,
        public readonly int $donationCents = 0,
        public readonly int $addOnCents = 0,
        public readonly int $taxCents = 0,
    ) {
        foreach ([
            'ticket subtotal' => $ticketSubtotalCents,
            'discount' => $discountCents,
            'merchandise' => $merchandiseCents,
            'donation' => $donationCents,
            'add-on' => $addOnCents,
            'tax' => $taxCents,
        ] as $label => $amount) {
            if ($amount < 0) {
                throw new InvalidArgumentException('The '.$label.' cannot be negative.');
            }
        }
    }

    /**
     * @param  list<int>  $ticketLineCents
     */
    public static function fromTickets(
        array $ticketLineCents,
        int $discountCents = 0,
        int $merchandiseCents = 0,
        int $donationCents = 0,
        int $addOnCents = 0,
        int $taxCents = 0,
    ): self {
        $subtotal = 0;

        foreach ($ticketLineCents as $cents) {
            if ($cents < 0) {
                throw new InvalidArgumentException('Ticket line amounts must be non-negative integers in cents.');
            }

            $subtotal += $cents;
        }

        return new self($subtotal, $discountCents, $merchandiseCents, $donationCents, $addOnCents, $taxCents);
    }

    public function eligibleCents(): int
    {
        return max(0, $this->ticketSubtotalCents - $this->discountCents);
    }
}
