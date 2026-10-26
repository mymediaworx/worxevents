<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Domain\Catalog\CheckoutRejected;
use App\Domain\Catalog\EventPageData;
use App\Domain\Catalog\FreeCheckout;
use App\Domain\Catalog\InventoryBook;
use App\Domain\Catalog\PaidCheckout;
use App\Domain\Tickets\TicketMailer;
use App\Enums\EventStatus;
use App\Enums\TicketVisibility;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class CheckoutController extends Controller
{
    public function __construct(
        private readonly FreeCheckout $checkout,
        private readonly PaidCheckout $paid,
        private readonly InventoryBook $inventory,
        private readonly EventPageData $pages,
        private readonly TicketMailer $mailer,
    ) {}

    public function hold(Request $request, string $slug): Response
    {
        $event = $this->event($request, $slug);
        $type = TicketType::query()
            ->where('event_id', $event->id)
            ->whereKey((string) $request->input('ticket_type_id'))
            ->first();

        if (! $type instanceof TicketType || $type->visibility === TicketVisibility::Hidden || $event->status !== EventStatus::Published) {
            return $this->page($event, 'That ticket cannot be reserved.', 422);
        }

        $quantity = (int) $request->input('quantity', 1);

        try {
            $hold = $this->inventory->reserve($type, $quantity);
        } catch (CheckoutRejected $exception) {
            return $this->page($event, $exception->getMessage(), $exception->status);
        }

        $data = $this->pages->for($event);
        $data['holdToken'] = $hold->token;
        $data['holdExpires'] = $hold->reservation->expires_at;
        $data['robots'] = 'noindex, nofollow';

        return response()->view('public.event', $data);
    }

    public function quote(Request $request, string $slug): Response
    {
        $event = $this->event($request, $slug);

        try {
            $quote = $this->paid->preview($event, $this->lines($request));
        } catch (CheckoutRejected $exception) {
            return $this->page($event, $exception->getMessage(), $exception->status);
        }

        $data = $this->pages->for($event);
        $data['quote'] = $quote;
        $data['robots'] = 'noindex, nofollow';

        return response()->view('public.event', $data);
    }

    public function store(Request $request, string $slug): Response
    {
        $event = $this->event($request, $slug);
        $rejected = $this->rejectCardFields($request, $event);

        if ($rejected instanceof Response) {
            return $rejected;
        }

        $guestName = trim((string) $request->input('guest_name'));
        $guestEmail = trim((string) $request->input('guest_email'));

        if ($guestName === '' || filter_var($guestEmail, FILTER_VALIDATE_EMAIL) === false) {
            return $this->page($event, 'Enter the name and email for this registration.', 422);
        }

        $buyer = $request->user();
        $lines = $this->lines($request);
        $tokens = array_values(array_filter(array_map(
            trim(...),
            explode(',', (string) $request->input('hold_token', '')),
        ), fn (string $token): bool => $token !== ''));

        try {
            $result = $this->containsPaid($event, $lines)
                ? $this->paid->place(
                    $event,
                    $lines,
                    $this->answers($request),
                    $guestName,
                    $guestEmail,
                    $buyer instanceof User ? $buyer->id : null,
                    $tokens,
                    trim((string) $request->input('data_descriptor')),
                    trim((string) $request->input('data_value')),
                )
                : $this->checkout->place(
                    $event,
                    $lines,
                    $this->answers($request),
                    $guestName,
                    $guestEmail,
                    $buyer instanceof User ? $buyer->id : null,
                    $tokens,
                );
        } catch (CheckoutRejected $exception) {
            return $this->page($event, $exception->getMessage(), $exception->status);
        }

        $this->mailer->send($result['order'], $event->title, $result['passes']);

        return response()->view('public.confirmation', [
            'title' => 'Registration confirmed',
            'robots' => 'noindex, nofollow',
            'order' => $result['order']->load(['lines', 'attendees']),
            'eventTitle' => $event->title,
        ]);
    }

    private function event(Request $request, string $slug): Event
    {
        if ($request->attributes->get('host_kind') !== 'organization') {
            abort(404);
        }

        $event = Event::query()->where('slug', $slug)->first();

        if (! $event instanceof Event || ! $event->isPubliclyVisible()) {
            abort(404);
        }

        return $event;
    }

    private function page(Event $event, string $message, int $status): Response
    {
        $data = $this->pages->for($event, $message);

        return response()->view('public.event', $data, $status);
    }

    private function rejectCardFields(Request $request, Event $event): ?Response
    {
        foreach (['card_number', 'cardNumber', 'cvv', 'cvc', 'expiration', 'expiry', 'pan'] as $field) {
            if ($request->exists($field)) {
                return $this->page($event, 'Card data is never accepted. Submit an Accept.js payment nonce.', 422);
            }
        }

        return null;
    }

    /**
     * @param  list<array{ticket_type_id: string, quantity: int, attendees: list<array{name: string, email: string}>}>  $lines
     */
    private function containsPaid(Event $event, array $lines): bool
    {
        foreach ($lines as $line) {
            $type = TicketType::query()
                ->whereKey($line['ticket_type_id'])
                ->where('event_id', $event->id)
                ->first();

            if ($type instanceof TicketType && $type->price_cents > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{ticket_type_id: string, quantity: int, attendees: list<array{name: string, email: string}>}>
     */
    private function lines(Request $request): array
    {
        $raw = $request->input('lines', []);

        if (! is_array($raw)) {
            return [];
        }

        $lines = [];

        foreach ($raw as $typeId => $line) {
            if (! is_string($typeId) || ! is_array($line)) {
                continue;
            }

            $quantity = (int) ($line['quantity'] ?? 0);

            if ($quantity < 1) {
                continue;
            }

            $people = [];
            $attendees = is_array($line['attendees'] ?? null) ? array_values($line['attendees']) : [];

            for ($index = 0; $index < $quantity; $index++) {
                $person = $attendees[$index] ?? [];
                $people[] = [
                    'name' => is_array($person) ? (string) ($person['name'] ?? '') : '',
                    'email' => is_array($person) ? (string) ($person['email'] ?? '') : '',
                ];
            }

            $lines[] = [
                'ticket_type_id' => $typeId,
                'quantity' => $quantity,
                'attendees' => $people,
            ];
        }

        return $lines;
    }

    /**
     * @return array<string, string>
     */
    private function answers(Request $request): array
    {
        $raw = $request->input('answers', []);

        if (! is_array($raw)) {
            return [];
        }

        $answers = [];

        foreach ($raw as $id => $value) {
            if (is_string($id) && (is_string($value) || is_numeric($value))) {
                $answers[$id] = (string) $value;
            }
        }

        return $answers;
    }
}
