@extends('layouts.app')

@section('content')
    <h1>{{ $event->title }}</h1>
    <p><a href="{{ route('events.edit', $event) }}">Back to the event</a></p>
    @if (! empty($status))
        <p class="status" role="status">{{ $status }}</p>
    @endif
    @if (! empty($message))
        <p class="alert" role="alert">{{ $message }}</p>
    @endif

    <h2>Recurring sessions</h2>
    <p>New sessions use timed entry and stay inside the event's start and end.</p>
    <form method="post" action="{{ route('events.series', $event) }}" class="stack">
        @csrf
        <label>Frequency
            <select name="frequency">
                <option value="weekly">Weekly</option>
                <option value="daily">Daily</option>
            </select>
        </label>
        <label>Repeat until
            <input type="date" name="until" required>
        </label>
        <button class="button" type="submit">Generate sessions</button>
    </form>

    <h2>Discounts</h2>
    <ul>
        @forelse ($discounts as $discount)
            <li>{{ $discount->code }} · {{ $discount->kind }} · {{ $discount->amount }} · used {{ $discount->redeemed_count }}</li>
        @empty
            <li>No discount codes.</li>
        @endforelse
    </ul>
    <form method="post" action="{{ route('events.discounts.store', $event) }}" class="stack">
        @csrf
        <label>Code <input name="code" required maxlength="40"></label>
        <label>Kind
            <select name="kind">
                <option value="percent">Percent (basis points, 1000 = 10%)</option>
                <option value="fixed">Fixed amount in cents</option>
            </select>
        </label>
        <label>Amount <input name="amount" type="number" min="0" required></label>
        <label>Maximum redemptions <input name="max_redemptions" type="number" min="1"></label>
        <label>Expires <input name="expires_at" type="date"></label>
        <label>Limit to ticket
            <select name="ticket_type_id">
                <option value="">Any ticket</option>
                @foreach ($event->ticketTypes as $type)
                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                @endforeach
            </select>
        </label>
        <button class="button" type="submit">Save discount</button>
    </form>

    <h2>Add-ons</h2>
    <p>Add-ons are excluded from the booking fee.</p>
    <ul>
        @forelse ($event->addOns as $addOn)
            <li>{{ $addOn->name }} · {{ $addOn->price_cents }} cents · {{ $addOn->quantity_sold }}/{{ $addOn->quantity_total }}</li>
        @empty
            <li>No add-ons.</li>
        @endforelse
    </ul>
    <form method="post" action="{{ route('events.add-ons.store', $event) }}" class="stack">
        @csrf
        <label>Name <input name="name" required></label>
        <label>Price in cents <input name="price_cents" type="number" min="0" required></label>
        <label>Quantity <input name="quantity_total" type="number" min="1" required></label>
        <button class="button" type="submit">Save add-on</button>
    </form>

    <h2>Reserved seats</h2>
    <p>Seat maps are edited as sections, rows, and seats. A visual designer is not included.</p>
    @if ($event->seatingPlan)
        <p>{{ $event->seatingPlan->name }}</p>
        <ul>
            @foreach ($event->seatingPlan->sections as $section)
                <li>
                    {{ $section->name }} · {{ $section->price_zone }}
                    <ul>
                        @foreach ($section->rows as $row)
                            <li>
                                Row {{ $row->label }}
                                <ul>
                                    @foreach ($row->seats as $seat)
                                        <li>
                                            Seat {{ $seat->label }}
                                            @if ($seat->accessible)
                                                (accessible)
                                            @endif
                                            · {{ $seat->status }}
                                        </li>
                                    @endforeach
                                </ul>
                            </li>
                        @endforeach
                    </ul>
                </li>
            @endforeach
        </ul>
        <form method="post" action="{{ route('events.seating.sections', $event) }}" class="stack">
            @csrf
            <label>Section <input name="name" required></label>
            <label>Price zone <input name="price_zone" required maxlength="40"></label>
            <button class="button" type="submit">Add section</button>
        </form>
        <form method="post" action="{{ route('events.seating.rows', $event) }}" class="stack">
            @csrf
            <label>Section
                <select name="seating_section_id">
                    @foreach ($event->seatingPlan->sections as $section)
                        <option value="{{ $section->id }}">{{ $section->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Row label <input name="label" required maxlength="16"></label>
            <button class="button" type="submit">Add row</button>
        </form>
        <form method="post" action="{{ route('events.seating.seats', $event) }}" class="stack">
            @csrf
            <label>Row
                <select name="seating_row_id">
                    @foreach ($event->seatingPlan->sections as $section)
                        @foreach ($section->rows as $row)
                            <option value="{{ $row->id }}">{{ $section->name }} {{ $row->label }}</option>
                        @endforeach
                    @endforeach
                </select>
            </label>
            <label>Seat label <input name="label" required maxlength="16"></label>
            <label>Accessible seat
                <input type="checkbox" name="accessible" value="1">
            </label>
            <button class="button" type="submit">Add seat</button>
        </form>
    @else
        <form method="post" action="{{ route('events.seating.plan', $event) }}" class="stack">
            @csrf
            <label>Plan name <input name="name" required></label>
            <button class="button" type="submit">Create seating plan</button>
        </form>
    @endif

    <h2>Waitlist</h2>
    <ul>
        @forelse ($waitlist as $entry)
            <li>
                {{ $entry->name }} · {{ $entry->email }} · {{ $entry->status }}
                @if ($entry->status === 'waiting')
                    <form method="post" action="{{ route('events.waitlist.offer', [$event, $entry]) }}">
                        @csrf
                        <button type="submit">Offer</button>
                    </form>
                @endif
            </li>
        @empty
            <li>Nobody is waiting.</li>
        @endforelse
    </ul>

    <h2>Transfers</h2>
    <p>A transfer voids the old token and emails a new one.</p>
    <ul>
        @forelse ($tickets as $ticket)
            <li>
                {{ $ticket->attendee->name }} · {{ $ticket->attendee->email }}
                <form method="post" action="{{ route('tickets.transfer', $ticket) }}" class="stack">
                    @csrf
                    <label>New name <input name="name" required></label>
                    <label>New email <input type="email" name="email" required></label>
                    <button type="submit">Transfer</button>
                </form>
            </li>
        @empty
            <li>No issued tickets.</li>
        @endforelse
    </ul>
@endsection
