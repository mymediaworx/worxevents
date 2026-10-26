<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Fees\BookingFeeCalculator;
use App\Domain\Fees\FeeBasis;
use App\Domain\Fees\FeeRuleResolver;
use App\Domain\Payments\AuthorizeNetGateway;
use App\Domain\Payments\ChargeInstruction;
use App\Domain\Payments\GatewayDisabled;
use App\Domain\Tickets\TicketPass;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\TicketType;
use App\Tenancy\OrganizationManager;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Paid checkout quotes the booking fee on the server, charges the organizer's
 * Authorize.net account with an Accept.js nonce, and issues tickets only
 * after that charge is approved.
 */
final class PaidCheckout
{
    public function __construct(
        private readonly CheckoutCart $cart,
        private readonly InventoryBook $inventory,
        private readonly OrderSettlement $settlement,
        private readonly AuthorizeNetGateway $gateway,
        private readonly BookingFeeCalculator $calculator,
        private readonly FeeRuleResolver $rules,
        private readonly OrganizationManager $organizations,
    ) {}

    /**
     * @param  list<array{ticket_type_id: string, quantity: int, attendees: list<array{name: string, email: string}>}>  $lines
     * @return array{ticket_subtotal_cents: int, fee_cents: int, total_cents: int, currency: string}
     */
    public function preview(Event $event, array $lines): array
    {
        $this->assertContext($event);
        $this->assertReady();

        $subtotal = 0;
        $paid = false;

        foreach ($lines as $line) {
            $type = TicketType::query()
                ->whereKey($line['ticket_type_id'])
                ->where('event_id', $event->id)
                ->first();

            if (! $type instanceof TicketType) {
                throw new CheckoutRejected('That ticket is not part of this event.', 404);
            }

            if ($type->price_cents > 0) {
                $paid = true;
            }

            $subtotal += $type->price_cents * $line['quantity'];
        }

        if (! $paid) {
            throw new CheckoutRejected('This registration has no paid tickets.');
        }

        $fee = $this->fee($event->organization_id, $subtotal);

        return [
            'ticket_subtotal_cents' => $subtotal,
            'fee_cents' => $fee,
            'total_cents' => $subtotal + $fee,
            'currency' => 'USD',
        ];
    }

    /**
     * @param  list<array{ticket_type_id: string, quantity: int, attendees: list<array{name: string, email: string}>}>  $lines
     * @param  array<string, string>  $answers
     * @param  list<string>  $holdTokens
     * @return array{order: Order, passes: list<TicketPass>}
     */
    public function place(
        Event $event,
        array $lines,
        array $answers,
        string $guestName,
        string $guestEmail,
        ?string $buyerUserId,
        array $holdTokens,
        string $dataDescriptor,
        string $dataValue,
    ): array {
        $this->assertContext($event);
        $this->assertReady();
        $this->assertOpaque($dataDescriptor, $dataValue);

        if ($lines === []) {
            throw new CheckoutRejected('Choose at least one ticket.');
        }

        $order = DB::transaction(function () use ($event, $lines, $answers, $guestName, $guestEmail, $buyerUserId, $holdTokens): Order {
            $lockedEvent = Event::query()->whereKey($event->id)->lockForUpdate()->first();

            if (! $lockedEvent instanceof Event) {
                throw new CheckoutRejected('This event is not available.', 404);
            }

            $prepared = $this->cart->prepareLines($lockedEvent, $lines, false);
            $answerRows = $this->cart->prepareAnswers($lockedEvent, $answers);
            $reservations = $this->cart->holdsFor($prepared, $holdTokens);
            $subtotal = $this->cart->subtotal($prepared);

            if ($subtotal < 1) {
                throw new CheckoutRejected('Paid checkout requires a ticket price.');
            }

            foreach ($reservations as $reservation) {
                if ($reservation->order_id !== null) {
                    throw new CheckoutRejected('Payment is already pending for this reservation. Tickets are issued after the processor confirms it.', 409);
                }
            }

            foreach ($prepared as $item) {
                if (! isset($reservations[$item['type']->id])) {
                    $hold = $this->inventory->reserve($item['type'], $item['quantity']);
                    $reservations[$item['type']->id] = $hold->reservation;
                }
            }

            $fee = $this->fee($lockedEvent->organization_id, $subtotal);
            $order = Order::query()->create([
                'organization_id' => $lockedEvent->organization_id,
                'buyer_user_id' => $buyerUserId,
                'guest_name' => $guestName,
                'guest_email' => strtolower($guestEmail),
                'status' => OrderStatus::Pending,
                'payment_status' => PaymentStatus::Pending,
                'provider' => 'authorize_net',
                'channel' => 'online',
                'currency' => 'USD',
                'ticket_subtotal_cents' => $subtotal,
                'discount_cents' => 0,
                'fee_cents' => $fee,
                'total_cents' => $subtotal + $fee,
                'reference' => $this->cart->reference(),
            ]);

            foreach ($prepared as $item) {
                $type = $item['type'];
                $sessionName = null;

                if ($type->session_id !== null) {
                    $sessionName = EventSession::query()->whereKey($type->session_id)->value('name');
                    $sessionName = is_string($sessionName) ? $sessionName : null;
                }

                $line = OrderLine::query()->create([
                    'organization_id' => $lockedEvent->organization_id,
                    'order_id' => $order->id,
                    'ticket_type_id' => $type->id,
                    'session_id' => $type->session_id,
                    'name_snapshot' => $type->name,
                    'kind_snapshot' => $type->kind->value,
                    'session_name_snapshot' => $sessionName,
                    'unit_price_cents' => $type->price_cents,
                    'quantity' => $item['quantity'],
                    'admits_snapshot' => $type->admits,
                    'line_total_cents' => $type->price_cents * $item['quantity'],
                ]);

                foreach ($item['attendees'] as $person) {
                    Attendee::query()->create([
                        'organization_id' => $lockedEvent->organization_id,
                        'order_id' => $order->id,
                        'order_line_id' => $line->id,
                        'event_id' => $lockedEvent->id,
                        'ticket_type_id' => $type->id,
                        'name' => $person['name'],
                        'email' => $person['email'],
                    ]);
                }
            }

            foreach ($answerRows as $row) {
                $order->answers()->create([
                    'organization_id' => $lockedEvent->organization_id,
                    'event_question_id' => $row['id'],
                    'value' => $row['value'],
                ]);
            }

            foreach ($reservations as $reservation) {
                $this->inventory->claim($reservation, $order->id);
            }

            return $order;
        });

        try {
            $result = $this->gateway->charge(new ChargeInstruction(
                organizationId: $order->organization_id,
                orderKey: $order->id,
                amountCents: $order->total_cents,
                currency: 'USD',
                idempotencyKey: $order->id,
                dataDescriptor: $dataDescriptor,
                dataValue: $dataValue,
                invoiceNumber: $order->reference,
                description: 'Tickets '.$order->reference,
            ));
        } catch (GatewayDisabled $exception) {
            throw new CheckoutRejected($exception->getMessage(), 409);
        } catch (Throwable) {
            throw new CheckoutRejected('The processor did not respond. Tickets are issued only after it confirms the charge.', 503);
        }

        if ($result->accepted && $result->status === 'approved' && is_string($result->reference) && $result->reference !== '') {
            $settled = $this->settlement->approve($order, $result->reference, $result->accountLastFour);

            return ['order' => $settled['order'], 'passes' => $settled['passes']];
        }

        if (! $result->accepted || $result->status === 'declined') {
            $this->settlement->decline($order);

            throw new CheckoutRejected('The card processor declined this payment. No tickets were issued.', 422);
        }

        throw new CheckoutRejected('The processor has not approved this payment. Tickets stay unissued.', 409);
    }

    private function fee(string $organizationId, int $subtotal): int
    {
        $rule = $this->rules->effective($organizationId, now());

        return $this->calculator->quote($rule->toData(), new FeeBasis($subtotal))->feeCents;
    }

    private function assertReady(): void
    {
        if (! $this->gateway->isReady()) {
            throw new CheckoutRejected('Paid tickets cannot be completed until the organizer connects a card processor.');
        }
    }

    private function assertContext(Event $event): void
    {
        if ($this->organizations->id() !== $event->organization_id) {
            throw new CheckoutRejected('This event is not on the current site.', 403);
        }
    }

    private function assertOpaque(string $descriptor, string $value): void
    {
        $allowed = ['COMMON.ACCEPT.INAPP.PAYMENT', 'COMMON.ACCEPT.PAYMENT'];

        if (! in_array($descriptor, $allowed, true) || strlen($value) < 10 || strlen($value) > 8192) {
            throw new CheckoutRejected('Card data is never accepted. Submit an Accept.js payment nonce.');
        }
    }
}
