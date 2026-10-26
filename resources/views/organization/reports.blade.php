@extends('layouts.app')

@section('content')
    <h1>Reports</h1>
    <ul>
        <li>Published events: {{ $summary['published_events'] }}</li>
        <li>Completed registrations: {{ $summary['completed_orders'] }}</li>
        <li>Tickets issued: {{ $summary['tickets_issued'] }}</li>
        <li>Ticket subtotal: ${{ \App\Domain\Fees\MoneyFormat::centsToDollars($summary['ticket_subtotal_cents']) }}</li>
        <li>Booking fees: ${{ \App\Domain\Fees\MoneyFormat::centsToDollars($summary['fee_cents']) }}</li>
    </ul>
    <table>
        <caption>Events</caption>
        <thead>
            <tr>
                <th>Event</th>
                <th>Status</th>
                <th>Sold admits</th>
                <th>Held admits</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($events as $event)
                <tr>
                    <td>{{ $event->title }}</td>
                    <td>{{ $event->status->value }}</td>
                    <td>{{ $event->admits_sold }}</td>
                    <td>{{ $event->admits_held }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
