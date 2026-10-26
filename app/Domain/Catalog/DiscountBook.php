<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Fees\PercentOfCents;
use App\Models\DiscountCode;
use App\Models\Event;

final class DiscountBook
{
    /**
     * @param  list<string>  $ticketTypeIds
     */
    public function redeem(Event $event, int $subtotal, array $ticketTypeIds, ?string $code): int
    {
        return $this->amount($event, $subtotal, $ticketTypeIds, $code, true);
    }

    /**
     * @param  list<string>  $ticketTypeIds
     */
    public function amount(Event $event, int $subtotal, array $ticketTypeIds, ?string $code, bool $commit): int
    {
        $code = strtoupper(trim((string) $code));

        if ($code === '') {
            return 0;
        }

        $query = DiscountCode::query()
            ->where('event_id', $event->id)
            ->where('code', $code);
        $row = $commit ? $query->lockForUpdate()->first() : $query->first();

        if (! $row instanceof DiscountCode) {
            throw new CheckoutRejected('That discount code is not valid.');
        }

        if ($row->expires_at !== null && ! $row->expires_at->isFuture()) {
            throw new CheckoutRejected('That discount code has expired.');
        }

        if ($row->max_redemptions !== null && $row->redeemed_count >= $row->max_redemptions) {
            throw new CheckoutRejected('That discount code has reached its limit.');
        }

        if ($row->ticket_type_id !== null && ! in_array($row->ticket_type_id, $ticketTypeIds, true)) {
            throw new CheckoutRejected('That discount code does not apply to these tickets.');
        }

        $discount = $row->kind === 'percent'
            ? PercentOfCents::apply($subtotal, $row->amount)
            : min($row->amount, $subtotal);

        if ($commit) {
            $redeemed = $row->redeemed_count + 1;
            $row->redeemed_count = $redeemed;
            $row->save();
        }

        return $discount;
    }

    public function restore(Event $event, ?string $code): void
    {
        $code = strtoupper(trim((string) $code));

        if ($code === '') {
            return;
        }

        $row = DiscountCode::query()
            ->where('event_id', $event->id)
            ->where('code', $code)
            ->lockForUpdate()
            ->first();

        if (! $row instanceof DiscountCode || $row->redeemed_count < 1) {
            return;
        }

        $row->redeemed_count = $row->redeemed_count - 1;
        $row->save();
    }
}
