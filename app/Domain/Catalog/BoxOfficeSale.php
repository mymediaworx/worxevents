<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Fees\BookingFeeRecorder;
use App\Domain\Fees\FeeBasis;
use App\Domain\Tickets\PassFactory;
use App\Domain\Tickets\TicketPass;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\TicketType;
use App\Models\User;
use App\Services\AuditLogger;
use App\Tenancy\OrganizationManager;
use Illuminate\Support\Facades\DB;

/**
 * Walk-up sales share the same inventory counters as online checkout.
 * Cash and card are records of money taken at the door. They are not sent
 * to a processor. Comps are $0. The booking fee is not assessed (decision D10).
 */
final class BoxOfficeSale
{
    public function __construct(
        private readonly InventoryBook $inventory,
        private readonly CheckoutCart $cart,
        private readonly PassFactory $passes,
        private readonly BookingFeeRecorder $fees,
        private readonly AuditLogger $audit,
        private readonly OrganizationManager $organizations,
    ) {}

    /**
     * @param  list<array{name: string, email: string}>  $people
     * @return array{order: Order, passes: list<TicketPass>}
     */
    public function sell(
        Event $event,
        TicketType $type,
        array $people,
        string $buyerName,
        string $buyerEmail,
        string $tender,
        ?User $seller,
    ): array {
        if ($this->organizations->id() !== $event->organization_id) {
            throw new CheckoutRejected('This event is not on the current site.', 403);
        }

        if (! in_array($tender, ['cash', 'card', 'comp'], true)) {
            throw new CheckoutRejected('Choose cash, card, or comp.');
        }

        if ($people === []) {
            throw new CheckoutRejected('Enter a name and email for each ticket.');
        }

        /** @var list<TicketPass> $issued */
        $issued = [];

        $order = DB::transaction(function () use ($event, $type, $people, $buyerName, $buyerEmail, $tender, $seller, &$issued): Order {
            $lockedEvent = Event::query()->whereKey($event->id)->lockForUpdate()->first();

            if (! $lockedEvent instanceof Event) {
                throw new CheckoutRejected('This event is not available.', 404);
            }

            $prepared = $this->cart->prepareLines($lockedEvent, [[
                'ticket_type_id' => $type->id,
                'quantity' => count($people),
                'attendees' => $people,
            ]], false);

            $item = $prepared[0];
            $hold = $this->inventory->reserve($item['type'], $item['quantity']);
            $unit = $tender === 'comp' ? 0 : $item['type']->price_cents;
            $subtotal = $unit * $item['quantity'];

            $order = Order::query()->create([
                'organization_id' => $lockedEvent->organization_id,
                'buyer_user_id' => null,
                'guest_name' => $buyerName,
                'guest_email' => strtolower($buyerEmail),
                'status' => OrderStatus::Completed,
                'payment_status' => $tender === 'comp' ? PaymentStatus::None : PaymentStatus::Approved,
                'channel' => 'box_office',
                'tender' => $tender,
                'currency' => 'USD',
                'ticket_subtotal_cents' => $subtotal,
                'discount_cents' => 0,
                'fee_cents' => 0,
                'total_cents' => $subtotal,
                'reference' => $this->cart->reference(),
                'completed_at' => now(),
            ]);

            $line = OrderLine::query()->create([
                'organization_id' => $lockedEvent->organization_id,
                'order_id' => $order->id,
                'ticket_type_id' => $item['type']->id,
                'session_id' => $item['type']->session_id,
                'name_snapshot' => $item['type']->name,
                'kind_snapshot' => $item['type']->kind->value,
                'unit_price_cents' => $unit,
                'quantity' => $item['quantity'],
                'admits_snapshot' => $item['type']->admits,
                'line_total_cents' => $subtotal,
            ]);

            foreach ($item['attendees'] as $person) {
                $attendee = Attendee::query()->create([
                    'organization_id' => $lockedEvent->organization_id,
                    'order_id' => $order->id,
                    'order_line_id' => $line->id,
                    'event_id' => $lockedEvent->id,
                    'ticket_type_id' => $item['type']->id,
                    'name' => $person['name'],
                    'email' => $person['email'],
                ]);
                $issued[] = $this->passes->issue($attendee, $lockedEvent, $order->reference);
            }

            $this->inventory->consume($hold->reservation, $order->id);
            $this->fees->record($lockedEvent->organization_id, $order->id, new FeeBasis(0), now());
            $this->audit->write('box_office.sold', $lockedEvent->organization_id, $order, [
                'reference' => $order->reference,
                'tender' => $tender,
                'total_cents' => $subtotal,
                'seller_id' => $seller?->id,
                'tickets' => count($issued),
            ]);

            return $order;
        });

        return ['order' => $order, 'passes' => $issued];
    }
}
