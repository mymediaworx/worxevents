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
    <h2>Booking fee ledger</h2>
    <ul>
        <li>Assessed: ${{ \App\Domain\Fees\MoneyFormat::centsToDollars($ledger['assessed']) }}</li>
        <li>Collected: ${{ \App\Domain\Fees\MoneyFormat::centsToDollars($ledger['collected']) }}</li>
        <li>Owed: ${{ \App\Domain\Fees\MoneyFormat::centsToDollars($ledger['owed']) }}</li>
        <li>Remitted: ${{ \App\Domain\Fees\MoneyFormat::centsToDollars($ledger['remitted']) }}</li>
        <li>Outstanding: ${{ \App\Domain\Fees\MoneyFormat::centsToDollars($ledger['outstanding']) }}</li>
        <li>Open invoices: ${{ \App\Domain\Fees\MoneyFormat::centsToDollars($ledger['open_invoices']) }}</li>
    </ul>
    <p><a href="{{ route('reports.csv') }}">Download financial CSV</a></p>
    @if (! empty($status))
        <p class="status" role="status">{{ $status }}</p>
    @endif
    @if (! empty($message))
        <p class="alert" role="alert">{{ $message }}</p>
    @endif
    @can('finance.refunds.decide')
        <form method="post" action="{{ route('reports.remittance') }}">
            @csrf
            <button class="button" type="submit">Invoice outstanding fees</button>
        </form>
    @endcan
    <h2>Remittance invoices</h2>
    <ul>
        @forelse ($invoices as $invoice)
            <li>
                {{ $invoice->reference }} · ${{ \App\Domain\Fees\MoneyFormat::centsToDollars($invoice->amount_cents) }} · {{ $invoice->status }}
                @if ($invoice->status === 'open' && auth()->user()->can('finance.refunds.decide'))
                    <form method="post" action="{{ route('reports.remittance.paid', $invoice) }}">
                        @csrf
                        <label>Payment reference
                            <input name="payment_reference" required maxlength="64">
                        </label>
                        <button type="submit">Mark remitted</button>
                    </form>
                @endif
            </li>
        @empty
            <li>No invoices.</li>
        @endforelse
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
