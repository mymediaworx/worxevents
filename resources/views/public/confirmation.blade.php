@extends('layouts.guest')

@section('content')
    <h1>You're registered</h1>
    <p class="lede">{{ $eventTitle }} · reference {{ $order->reference }}.</p>
    <p>Tickets were sent to {{ $order->guest_email }}. The message includes a PDF and a QR code. The code is a random token. This page does not repeat it.</p>
    <h2>Attendees</h2>
    <ul>
        @foreach ($order->attendees as $attendee)
            <li>{{ $attendee->name }} · {{ $attendee->email }}</li>
        @endforeach
    </ul>
@endsection
