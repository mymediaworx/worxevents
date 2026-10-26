@extends('layouts.app')

@section('content')
    <h1>Dashboard</h1>
    <p class="lede">Signed in as {{ auth()->user()->name }} · {{ $membership->role->name ?? 'Member' }}.</p>
    @can('organization.settings.update')
        @if ($organization->setup_completed_at === null)
            <p><a href="{{ route('setup') }}">Finish organization setup</a>. Details, logo, currency, and time zone can be saved as a draft.</p>
        @endif
    @endcan
    <p>Free registration is open. Paid checkout uses Authorize.net after sandbox credentials are saved under Payments. Stripe is not connected.</p>
@endsection
