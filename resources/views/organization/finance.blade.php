@extends('layouts.app')

@section('content')
    <h1>Finance</h1>
    <p class="lede">Refunds stay incomplete until the payment provider confirms them. The booking fee ledger is separate from this decision.</p>
    @include('partials.errors')
    @if (! empty($status))
        <p class="status" role="status">{{ $status }}</p>
    @endif
    @if (! empty($message))
        <p class="alert" role="alert">{{ $message }}</p>
    @endif

    @can('finance.refunds.decide')
        <form method="post" action="{{ route('refunds.store') }}" class="stack">
            @csrf
            <label for="order_key">Order key</label>
            <input id="order_key" name="order_key" required maxlength="64">
            <label for="amount">Amount in dollars</label>
            <input id="amount" name="amount" inputmode="decimal" required value="10.00">
            <label for="note">Decision note</label>
            <textarea id="note" name="note" required maxlength="500"></textarea>
            <button class="button" type="submit">Request refund</button>
        </form>
    @endcan

    <h2>Refunds</h2>
    <ul>
        @forelse ($refunds as $refund)
            <li>
                {{ $refund->order_key }} · ${{ \App\Domain\Fees\MoneyFormat::centsToDollars($refund->amount_cents) }}
                · {{ $refund->status->value }}
                @if ($refund->status->value === 'requested' && auth()->user()->can('finance.refunds.decide'))
                    <form method="post" action="{{ route('refunds.submit', $refund) }}">
                        @csrf
                        <button type="submit">Submit to provider</button>
                    </form>
                @endif
                @if ($refund->status->value === 'submitted' && auth()->user()->can('finance.refunds.decide'))
                    <form method="post" action="{{ route('refunds.confirm', $refund) }}">
                        @csrf
                        <button type="submit">Check provider confirmation</button>
                    </form>
                @endif
            </li>
        @empty
            <li>No refund requests.</li>
        @endforelse
    </ul>
@endsection
