<?php

declare(strict_types=1);

namespace App\Domain\Refunds;

use App\Enums\IssuedTicketStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Order;
use App\Models\RefundRequest;
use App\Models\Ticket;

/**
 * A confirmed refund voids tickets once the refunded amount covers the ticket
 * subtotal. The booking fee ledger is left alone (decision D2).
 */
final class RefundCompletion
{
    public function apply(RefundRequest $refund): void
    {
        if ($refund->status !== RefundStatus::Completed) {
            return;
        }

        $order = Order::query()->whereKey($refund->order_key)->first();

        if (! $order instanceof Order) {
            return;
        }

        $refunded = 0;
        $rows = RefundRequest::query()
            ->where('order_key', $order->id)
            ->where('status', RefundStatus::Completed->value)
            ->get(['amount_cents']);

        foreach ($rows as $row) {
            $refunded += $row->amount_cents;
        }

        if ($order->ticket_subtotal_cents > 0 && $refunded >= $order->ticket_subtotal_cents) {
            Ticket::query()
                ->where('order_id', $order->id)
                ->where('status', IssuedTicketStatus::Issued->value)
                ->update(['status' => IssuedTicketStatus::Void->value]);
            $order->payment_status = PaymentStatus::Refunded;
        } else {
            $order->payment_status = PaymentStatus::PartiallyRefunded;
        }

        $order->save();
    }
}
