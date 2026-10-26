@extends('layouts.app')

@section('content')
    <h1>Your tickets</h1>
    @if ($orders->isEmpty())
        <p>You have no registrations on this organization yet.</p>
    @else
        <ul>
            @foreach ($orders as $order)
                <li>
                    {{ $order->reference }} · {{ $order->guest_email }}
                    <ul>
                        @foreach ($order->lines as $line)
                            <li>{{ $line->name_snapshot }} · {{ $line->quantity }}</li>
                        @endforeach
                    </ul>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
