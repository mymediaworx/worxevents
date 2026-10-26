@extends('layouts.guest')

@section('content')
    @if (!empty($preview))
        <p class="status">Draft preview. This page is not indexed and is not on the public site.</p>
    @endif
    @if (!empty($checkoutError))
        <p class="alert" role="alert">{{ $checkoutError }}</p>
    @endif
    @isset($holdToken)
        <p class="status" role="status">Reservation code <strong>{{ $holdToken }}</strong>@isset($holdExpires) expires {{ $holdExpires->timezone($event->timezone)->format('M j, g:i A T') }}@endisset. Enter it when you register. It is shown only here.</p>
    @endisset
    <p class="eyebrow">{{ $event->category->name ?? 'Event' }}</p>
    <h1>{{ $event->title }}</h1>
    <p class="meta">
        {{ $event->starts_at->timezone($event->timezone)->format('l, M j, Y g:i A T') }}
        –
        {{ $event->ends_at->timezone($event->timezone)->format('M j, Y g:i A T') }}
        @if ($event->is_multi_day)
            · Multi-day
        @endif
    </p>
    <p>{{ $saleMessage }}</p>
    @if ($event->summary)
        <p class="lede">{{ $event->summary }}</p>
    @endif
    @isset($ogImage)
        <p><img src="{{ $ogImage }}" alt=""></p>
    @endisset
    @if ($event->description)
        <div>{!! nl2br(e($event->description)) !!}</div>
    @endif
    @if ($event->venue && $event->organization)
        <p>Venue: <a href="{{ route('public.platform.venue', ['organization' => $event->organization->slug, 'venue' => $event->venue->slug]) }}">{{ $event->venue->name }}</a>
            @if ($event->venue->addressLine() !== '')
                — {{ $event->venue->addressLine() }}
            @endif
        </p>
    @endif
    @if ($event->organization)
        <p>Organizer: <a href="{{ route('public.organizer', $event->organization->slug) }}">{{ $event->organization->name }}</a></p>
    @endif
    @if ($event->sessions->isNotEmpty())
        <h2>Sessions</h2>
        <ul>
            @foreach ($event->sessions as $session)
                <li>{{ $session->name }} · {{ $session->starts_at->timezone($event->timezone)->format('M j, g:i A') }}</li>
            @endforeach
        </ul>
    @endif
    @if ($event->tags->isNotEmpty())
        <p class="meta">Tags:
            @foreach ($event->tags as $tag)
                <a href="{{ route('browse', ['tag' => $tag->slug]) }}">{{ $tag->name }}</a>
            @endforeach
        </p>
    @endif
    <h2>Tickets</h2>
    <ul>
        @foreach ($event->ticketTypes as $type)
            @if ($type->visibility->value === 'hidden' && empty($preview))
                @continue
            @endif
            <li>
                {{ $type->name }} · {{ $type->kind->label() }} ·
                {{ $type->price_cents === 0 ? 'Free' : '$'.\App\Domain\Fees\MoneyFormat::centsToDollars($type->price_cents) }}
                · {{ $type->remaining() }} left
                @if ($type->visibility->value !== 'public')
                    · {{ $type->visibility->value }}
                @endif
            </li>
        @endforeach
    </ul>
    @if ($canRegister)
        <h2>Register</h2>
        <form method="post" action="{{ route('checkout.store', $event->slug) }}" class="stack">
            @csrf
            <label>Your name
                <input name="guest_name" required autocomplete="name" value="{{ old('guest_name') }}">
            </label>
            <label>Your email
                <input name="guest_email" type="email" required autocomplete="email" value="{{ old('guest_email') }}">
            </label>
            <label>Reservation code
                <input name="hold_token" value="{{ old('hold_token', $holdToken ?? '') }}" autocomplete="off">
            </label>
            @foreach ($registerable as $type)
                <fieldset>
                    <legend>{{ $type->name }} · Free · up to {{ $type->max_per_order }}</legend>
                    <label>Quantity
                        <input type="number" name="lines[{{ $type->id }}][quantity]" min="0" max="{{ $type->max_per_order }}" value="0">
                    </label>
                    @for ($person = 0; $person < $type->max_per_order; $person++)
                        <label>Attendee {{ $person + 1 }} name
                            <input name="lines[{{ $type->id }}][attendees][{{ $person }}][name]" autocomplete="name">
                        </label>
                        <label>Attendee {{ $person + 1 }} email
                            <input type="email" name="lines[{{ $type->id }}][attendees][{{ $person }}][email]" autocomplete="email">
                        </label>
                    @endfor
                </fieldset>
            @endforeach
            @foreach ($event->questions as $question)
                <label>{{ $question->label }}@if ($question->required) (required)@endif
                    @if ($question->field_type->value === 'textarea')
                        <textarea name="answers[{{ $question->id }}]" @if ($question->required) required @endif>{{ old('answers.'.$question->id) }}</textarea>
                    @elseif ($question->field_type->value === 'select')
                        <select name="answers[{{ $question->id }}]" @if ($question->required) required @endif>
                            <option value="">Choose</option>
                            @foreach ($question->options ?? [] as $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </select>
                    @elseif ($question->field_type->value === 'checkbox')
                        <input type="checkbox" name="answers[{{ $question->id }}]" value="yes">
                    @else
                        <input name="answers[{{ $question->id }}]" @if ($question->required) required @endif value="{{ old('answers.'.$question->id) }}">
                    @endif
                </label>
            @endforeach
            <button class="button" type="submit">Complete free registration</button>
        </form>
        <form method="post" action="{{ route('checkout.hold', $event->slug) }}" class="stack">
            @csrf
            <p>Hold tickets for {{ (int) config('worxevents.reservation_minutes', 15) }} minutes before registering.</p>
            <label>Ticket
                <select name="ticket_type_id">
                    @foreach ($registerable as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Quantity
                <input type="number" name="quantity" min="1" value="1">
            </label>
            <button class="button button-quiet" type="submit">Reserve</button>
        </form>
    @endif
@endsection
