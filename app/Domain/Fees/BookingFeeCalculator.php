<?php

declare(strict_types=1);

namespace App\Domain\Fees;

/**
 * Pure booking-fee calculation.
 *
 * fee = MIN(maximum, MAX(minimum, eligible * percent))
 * eligible = MAX(0, ticket subtotal - discount)
 *
 * A zero eligible subtotal is a free order and the fee is 0. The minimum is
 * not applied to free orders. Merchandise, donations, add-ons, and tax are
 * not part of the eligible amount. One call prices one order, so several
 * tickets produce one fee.
 */
final class BookingFeeCalculator
{
    public function quote(FeeRuleData $rule, FeeBasis $basis): FeeQuote
    {
        $eligible = $basis->eligibleCents();

        if ($eligible === 0) {
            return new FeeQuote(0, 0, 0, $rule);
        }

        $raw = PercentOfCents::apply($eligible, $rule->percentBps);
        $fee = max($rule->minimumCents, $raw);
        $fee = min($rule->maximumCents, $fee);

        return new FeeQuote($fee, $eligible, $raw, $rule);
    }
}
