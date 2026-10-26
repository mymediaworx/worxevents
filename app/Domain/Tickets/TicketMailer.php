<?php

declare(strict_types=1);

namespace App\Domain\Tickets;

use App\Mail\TicketDelivery;
use App\Models\Order;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class TicketMailer
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  list<TicketPass>  $passes
     */
    public function send(Order $order, string $eventTitle, array $passes): void
    {
        try {
            Mail::to($order->guest_email)->send(new TicketDelivery(
                $eventTitle,
                $order->guest_name,
                $order->reference,
                url('/registration/'.$order->reference),
                $passes,
            ));
        } catch (Throwable) {
            $order->forceFill(['email_failed_at' => now()])->save();
            $this->audit->write('order.email_failed', $order->organization_id, $order, [
                'reference' => $order->reference,
            ]);
        }
    }
}
