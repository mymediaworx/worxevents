@extends('layouts.guest')

@section('content')
    <h1>Registration {{ $reference }}</h1>
    @if ($order === null)
        <p>Enter the email used at checkout to see this registration.</p>
        <form method="post" action="{{ route('registration.confirm', $reference) }}" class="stack">
            @csrf
            <label>Email
                <input name="email" type="email" required autocomplete="email">
            </label>
            <button class="button" type="submit">Show registration</button>
        </form>
    @else
        <p>Reference {{ $order->reference }} for {{ $order->guest_name }}.</p>
        <ul>
            @foreach ($order->lines as $line)
                <li>{{ $line->name_snapshot }} · {{ $line->quantity }} · {{ $line->unit_price_cents === 0 ? 'Free' : '$'.\App\Domain\Fees\MoneyFormat::centsToDollars($line->unit_price_cents) }}</li>
            @endforeach
        </ul>
        <h2>Attendees</h2>
        <ul>
            @foreach ($order->attendees as $attendee)
                <li>{{ $attendee->name }}</li>
            @endforeach
        </ul>
    @endif
@endsection
