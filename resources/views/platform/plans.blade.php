@extends('layouts.platform')

@section('content')
    <h1>Plans</h1>
    <p>Plans describe what an organization is offered. Saving a plan does not charge a card.</p>
    @if (! empty($status))
        <p class="status" role="status">{{ $status }}</p>
    @endif
    <ul>
        @forelse ($plans as $plan)
            <li>{{ $plan->name }} · ${{ \App\Domain\Fees\MoneyFormat::centsToDollars($plan->amount_cents) }} · {{ $plan->active ? 'active' : 'inactive' }}</li>
        @empty
            <li>No plans.</li>
        @endforelse
    </ul>
    @can('platform.settings.manage')
        <form method="post" action="{{ route('platform.plans.store') }}" class="stack">
            @csrf
            <label>Name <input name="name" required></label>
            <label>Description <input name="description" maxlength="500"></label>
            <label>Amount in cents <input name="amount_cents" type="number" min="0" required></label>
            <label>Active <input type="checkbox" name="active" value="1" checked></label>
            <button class="button" type="submit">Save plan</button>
        </form>
    @endcan
@endsection
