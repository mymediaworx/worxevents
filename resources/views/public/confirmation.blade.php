@extends('layouts.guest')

@section('content')
    <h1>You're registered</h1>
    <p class="lede">{{ $eventTitle }} · reference {{ $order->reference }}.</p>
    <p>Tickets were sent to {{ $order->guest_email }}. The message includes a PDF and a QR code. The code is a random token. This page does not repeat it.</p>
    <h2>Total</h2>
    <ul>
        <li>Tickets ${{ \App\Domain\Fees\MoneyFormat::centsToDollars($order->ticket_subtotal_cents) }}</li>
        <li>{{ config('payments.fee_label') }} ${{ \App\Domain\Fees\MoneyFormat::centsToDollars($order->fee_cents) }}</li>
        <li>Total ${{ \App\Domain\Fees\MoneyFormat::centsToDollars($order->total_cents) }}</li>
    </ul>
    <h2>Attendees</h2>
    <ul>
        @foreach ($order->attendees as $attendee)
            <li>{{ $attendee->name }} · {{ $attendee->email }}</li>
        @endforeach
    </ul>
@endsection
