@extends('layouts.app')

@section('content')
    <h1>Payments</h1>
    <p class="lede">Authorize.net Accept.js charges the organizer's own merchant account. Live mode stays off until the platform enables it. Card numbers are not stored here.</p>
    @include('partials.errors')
    @if (! empty($status))
        <p class="status" role="status">{{ $status }}</p>
    @endif
    @if (! empty($message))
        <p class="alert" role="alert">{{ $message }}</p>
    @endif
    @if ($validated)
        <p>Connection validated for {{ $environment }}. API login ending in {{ $loginHint }}.</p>
    @endif
    <form method="post" action="{{ route('payments.store') }}" class="stack" autocomplete="off">
        @csrf
        <label>Environment
            <select name="environment">
                <option value="sandbox" @selected(old('environment', $environment) === 'sandbox')>Sandbox</option>
                @if ($liveEnabled)
                    <option value="live" @selected(old('environment', $environment) === 'live')>Live</option>
                @endif
            </select>
        </label>
        <p class="meta">Live mode is disabled until the platform sets it. These fields are write-only.</p>
        <label>API login ID
            <input name="api_login_id" required autocomplete="off" value="">
        </label>
        <label>Transaction key
            <input name="transaction_key" type="password" required autocomplete="new-password" value="">
        </label>
        <label>Signature key
            <input name="signature_key" type="password" required autocomplete="new-password" value="">
        </label>
        <label>Public client key
            <input name="public_client_key" required autocomplete="off" value="">
        </label>
        <button class="button" type="submit">Validate and save</button>
    </form>
@endsection
