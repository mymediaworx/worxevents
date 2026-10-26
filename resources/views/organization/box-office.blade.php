@extends('layouts.app')

@section('content')
    <h1>Box office</h1>
    <p class="lede">Walk-up sales use the same inventory as online checkout. Cash and card are records only. Comps are free. The booking fee is not added at the door.</p>
    @if (! empty($status))
        <p class="status" role="status">{{ $status }}</p>
    @endif
    @if (! empty($message))
        <p class="alert" role="alert">{{ $message }}</p>
    @endif
    <form method="post" action="{{ route('box-office.store') }}" class="stack">
        @csrf
        <label>Event
            <select name="event_id" required>
                <option value="">Choose</option>
                @foreach ($events as $event)
                    <option value="{{ $event->id }}">{{ $event->title }}</option>
                @endforeach
            </select>
        </label>
        <label>Ticket
            <select name="ticket_type_id" required>
                @foreach ($events as $event)
                    @foreach ($event->ticketTypes as $type)
                        <option value="{{ $type->id }}">{{ $event->title }} · {{ $type->name }} · ${{ \App\Domain\Fees\MoneyFormat::centsToDollars($type->price_cents) }}</option>
                    @endforeach
                @endforeach
            </select>
        </label>
        <label>Attendee name
            <input name="attendee_name" required autocomplete="name">
        </label>
        <label>Attendee email
            <input name="attendee_email" type="email" required autocomplete="email">
        </label>
        <fieldset>
            <legend>Tender</legend>
            <label><input type="radio" name="tender" value="cash" required> Cash</label>
            <label><input type="radio" name="tender" value="card"> Card record</label>
            <label><input type="radio" name="tender" value="comp"> Comp</label>
        </fieldset>
        <button class="button" type="submit">Sell ticket</button>
    </form>
@endsection
