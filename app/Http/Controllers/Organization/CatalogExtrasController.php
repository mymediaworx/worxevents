<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Domain\Catalog\CheckoutRejected;
use App\Domain\Catalog\SessionSeries;
use App\Domain\Tickets\TicketPass;
use App\Domain\Tickets\TicketTransfer;
use App\Http\Controllers\Controller;
use App\Mail\TicketDelivery;
use App\Models\AddOn;
use App\Models\DiscountCode;
use App\Models\Event;
use App\Models\Seat;
use App\Models\SeatingPlan;
use App\Models\SeatingRow;
use App\Models\SeatingSection;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\WaitlistEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Organizer tools for series, discounts, add-ons, reserved seats, waitlist
 * offers, and ticket transfers. Seat maps are edited as data. A visual
 * designer is not included.
 */
final class CatalogExtrasController extends Controller
{
    public function __construct(
        private readonly SessionSeries $series,
        private readonly TicketTransfer $transfers,
    ) {}

    public function show(Event $event): Response
    {
        return response()->view('organization.events.extras', $this->page($event));
    }

    public function storeSeries(Request $request, Event $event): Response
    {
        $validated = $this->validateOr422($request, [
            'frequency' => ['required', 'in:daily,weekly'],
            'until' => ['required', 'date'],
        ], 'organization.events.extras', $this->page($event));

        try {
            $count = $this->series->generate(
                $event,
                (string) $validated['frequency'],
                Carbon::parse((string) $validated['until'])->endOfDay(),
            );
        } catch (CheckoutRejected $exception) {
            return $this->fail($event, $exception);
        }

        return $this->ok($event, 'Created '.$count.' timed sessions.');
    }

    public function storeDiscount(Request $request, Event $event): Response
    {
        $validated = $this->validateOr422($request, [
            'code' => ['required', 'string', 'max:40'],
            'kind' => ['required', 'in:percent,fixed'],
            'amount' => ['required', 'integer', 'min:0', 'max:100000000'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date'],
            'ticket_type_id' => ['nullable', 'string'],
        ], 'organization.events.extras', $this->page($event));

        if ($validated['kind'] === 'percent' && (int) $validated['amount'] > 10000) {
            return $this->fail($event, new CheckoutRejected('A percent discount cannot exceed 10000 basis points.'));
        }

        $typeId = $this->ticketTypeId($event, $validated['ticket_type_id'] ?? null);

        if ($typeId === false) {
            return $this->fail($event, new CheckoutRejected('That ticket type is not part of this event.'));
        }

        DiscountCode::query()->create([
            'organization_id' => $event->organization_id,
            'event_id' => $event->id,
            'ticket_type_id' => $typeId,
            'code' => strtoupper(trim((string) $validated['code'])),
            'kind' => $validated['kind'],
            'amount' => (int) $validated['amount'],
            'max_redemptions' => $validated['max_redemptions'] ?? null,
            'expires_at' => isset($validated['expires_at']) ? Carbon::parse((string) $validated['expires_at']) : null,
        ]);

        return $this->ok($event, 'Discount saved.');
    }

    public function storeAddOn(Request $request, Event $event): Response
    {
        $validated = $this->validateOr422($request, [
            'name' => ['required', 'string', 'max:120'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'quantity_total' => ['required', 'integer', 'min:1'],
        ], 'organization.events.extras', $this->page($event));

        AddOn::query()->create([
            'organization_id' => $event->organization_id,
            'event_id' => $event->id,
            'name' => (string) $validated['name'],
            'price_cents' => (int) $validated['price_cents'],
            'quantity_total' => (int) $validated['quantity_total'],
        ]);

        return $this->ok($event, 'Add-on saved.');
    }

    public function storePlan(Request $request, Event $event): Response
    {
        $validated = $this->validateOr422($request, [
            'name' => ['required', 'string', 'max:120'],
        ], 'organization.events.extras', $this->page($event));

        if (SeatingPlan::query()->where('event_id', $event->id)->exists()) {
            return $this->fail($event, new CheckoutRejected('This event already has a seating plan.', 409));
        }

        SeatingPlan::query()->create([
            'organization_id' => $event->organization_id,
            'venue_id' => $event->venue_id,
            'event_id' => $event->id,
            'name' => (string) $validated['name'],
        ]);

        return $this->ok($event, 'Seating plan saved. A visual designer is not included.');
    }

    public function storeSection(Request $request, Event $event): Response
    {
        $plan = $this->plan($event);

        if (! $plan instanceof SeatingPlan) {
            return $this->fail($event, new CheckoutRejected('Add a seating plan first.'));
        }

        $validated = $this->validateOr422($request, [
            'name' => ['required', 'string', 'max:80'],
            'price_zone' => ['required', 'string', 'max:40'],
        ], 'organization.events.extras', $this->page($event));

        SeatingSection::query()->create([
            'organization_id' => $event->organization_id,
            'seating_plan_id' => $plan->id,
            'name' => (string) $validated['name'],
            'price_zone' => (string) $validated['price_zone'],
            'sort' => $plan->sections()->count(),
        ]);

        return $this->ok($event, 'Section saved.');
    }

    public function storeRow(Request $request, Event $event): Response
    {
        $plan = $this->plan($event);

        if (! $plan instanceof SeatingPlan) {
            return $this->fail($event, new CheckoutRejected('Add a seating plan first.'));
        }

        $validated = $this->validateOr422($request, [
            'seating_section_id' => ['required', 'string'],
            'label' => ['required', 'string', 'max:16'],
        ], 'organization.events.extras', $this->page($event));

        $section = SeatingSection::query()
            ->whereKey($validated['seating_section_id'])
            ->where('seating_plan_id', $plan->id)
            ->first();

        if (! $section instanceof SeatingSection) {
            return $this->fail($event, new CheckoutRejected('That section is not part of this plan.', 404));
        }

        SeatingRow::query()->create([
            'organization_id' => $event->organization_id,
            'seating_section_id' => $section->id,
            'label' => (string) $validated['label'],
            'sort' => $section->rows()->count(),
        ]);

        return $this->ok($event, 'Row saved.');
    }

    public function storeSeat(Request $request, Event $event): Response
    {
        $plan = $this->plan($event);

        if (! $plan instanceof SeatingPlan) {
            return $this->fail($event, new CheckoutRejected('Add a seating plan first.'));
        }

        $validated = $this->validateOr422($request, [
            'seating_row_id' => ['required', 'string'],
            'label' => ['required', 'string', 'max:16'],
        ], 'organization.events.extras', $this->page($event));

        $row = $this->row($plan, (string) $validated['seating_row_id']);

        if (! $row instanceof SeatingRow) {
            return $this->fail($event, new CheckoutRejected('That row is not part of this plan.', 404));
        }

        Seat::query()->create([
            'organization_id' => $event->organization_id,
            'seating_row_id' => $row->id,
            'label' => (string) $validated['label'],
            'accessible' => $request->boolean('accessible'),
            'status' => 'available',
        ]);

        return $this->ok($event, 'Seat saved.');
    }

    public function offer(Event $event, WaitlistEntry $entry): Response
    {
        if ($entry->event_id !== $event->id) {
            abort(404);
        }

        $entry->forceFill(['status' => 'offered'])->save();

        return $this->ok($event, 'Waitlist offer recorded. It does not purchase a ticket.');
    }

    public function transfer(Request $request, Ticket $ticket): Response
    {
        $event = Event::query()->whereKey($ticket->event_id)->first();

        if (! $event instanceof Event) {
            abort(404);
        }

        $validated = $this->validateOr422($request, [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
        ], 'organization.events.extras', $this->page($event));

        try {
            $pass = $this->transfers->transfer($ticket, (string) $validated['name'], (string) $validated['email']);
        } catch (CheckoutRejected $exception) {
            return $this->fail($event, $exception);
        }

        $this->deliver($event, (string) $validated['name'], (string) $validated['email'], $pass);

        return $this->ok($event, 'Ticket transferred. The previous token is void.');
    }

    /**
     * @return array<string, mixed>
     */
    private function page(Event $event): array
    {
        $event->load([
            'sessions',
            'ticketTypes',
            'addOns',
            'seatingPlan.sections.rows.seats',
        ]);

        return [
            'title' => 'Event tools',
            'event' => $event,
            'discounts' => DiscountCode::query()->where('event_id', $event->id)->orderBy('code')->get(),
            'waitlist' => WaitlistEntry::query()->where('event_id', $event->id)->orderBy('email')->get(),
            'tickets' => Ticket::query()->where('event_id', $event->id)->where('status', 'issued')->with('attendee')->limit(20)->get(),
        ];
    }

    private function ok(Event $event, string $status): Response
    {
        return response()->view('organization.events.extras', array_merge($this->page($event), [
            'status' => $status,
        ]));
    }

    private function fail(Event $event, CheckoutRejected $exception): Response
    {
        return response()->view('organization.events.extras', array_merge($this->page($event), [
            'message' => $exception->getMessage(),
        ]), $exception->status);
    }

    private function plan(Event $event): ?SeatingPlan
    {
        $plan = SeatingPlan::query()->where('event_id', $event->id)->first();

        return $plan instanceof SeatingPlan ? $plan : null;
    }

    private function row(SeatingPlan $plan, string $rowId): ?SeatingRow
    {
        $sectionIds = $plan->sections()->pluck('id');
        $row = SeatingRow::query()->whereKey($rowId)->whereIn('seating_section_id', $sectionIds)->first();

        return $row instanceof SeatingRow ? $row : null;
    }

    private function ticketTypeId(Event $event, mixed $id): string|false|null
    {
        if (! is_string($id) || $id === '') {
            return null;
        }

        $type = TicketType::query()->whereKey($id)->where('event_id', $event->id)->first();

        return $type instanceof TicketType ? $type->id : false;
    }

    private function deliver(Event $event, string $name, string $email, TicketPass $pass): void
    {
        try {
            Mail::to($email)->send(new TicketDelivery(
                $event->title,
                $name,
                'transfer',
                url('/e/'.$event->slug),
                [$pass],
            ));
        } catch (Throwable) {
            // The transfer is already saved. A later reissue can send a new token.
        }
    }
}
