@extends('layouts.platform')

@section('content')
    <h1>Fee rules</h1>
    <p class="lede">Rules are immutable. A new row applies to new orders on or after its UTC effective date. Existing snapshots stay as they were.</p>
    @include('partials.errors')
    @if (! empty($message))
        <p class="alert" role="alert">{{ $message }}</p>
    @endif
    <table>
        <thead>
            <tr>
                <th>Effective (UTC)</th>
                <th>Percent</th>
                <th>Minimum</th>
                <th>Maximum</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rules as $rule)
                <tr>
                    <td>{{ $rule->effective_at->timezone('UTC')->toDateString() }}</td>
                    <td>{{ \App\Domain\Fees\MoneyFormat::bpsToPercent($rule->percent_bps) }}%</td>
                    <td>${{ \App\Domain\Fees\MoneyFormat::centsToDollars($rule->minimum_cents) }}</td>
                    <td>${{ \App\Domain\Fees\MoneyFormat::centsToDollars($rule->maximum_cents) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @can('platform.fees.manage')
        <h2>New rule</h2>
        <form method="post" action="{{ route('platform.fees.store') }}" class="stack">
            @csrf
            <label for="percent">Percent</label>
            <input id="percent" name="percent" required value="3">
            <label for="minimum">Minimum dollars</label>
            <input id="minimum" name="minimum" required value="1">
            <label for="maximum">Maximum dollars</label>
            <input id="maximum" name="maximum" required value="5">
            <label for="effective_on">Effective date (UTC)</label>
            <input id="effective_on" name="effective_on" type="date" required>
            <label for="note">Note</label>
            <input id="note" name="note" maxlength="255">
            <button class="button" type="submit">Save rule</button>
        </form>
    @endcan
@endsection
