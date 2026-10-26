@extends('layouts.app')

@section('content')
    <div class="page-head">
        <div>
            <h1>Dashboard</h1>
            <p class="lede">Signed in as {{ auth()->user()->name }} · {{ $membership->role->name ?? 'Member' }}.</p>
        </div>
    </div>
    @can('organization.settings.update')
        @if ($organization->setup_completed_at === null)
            <p class="notice">Finish organization setup. Details, logo, currency, and time zone can be saved as a draft. <a href="{{ route('setup') }}">Open setup</a>.</p>
        @endif
    @endcan
    <div class="stat-grid">
        <a class="stat-card" href="{{ route('events') }}">
            <div class="stat-strip">Catalog</div>
            <div class="stat-body">
                <div class="stat-title">Events</div>
                <div class="stat-value">{{ $eventCount }}</div>
            </div>
        </a>
        <a class="stat-card" href="{{ route('venues') }}">
            <div class="stat-strip">Places</div>
            <div class="stat-body">
                <div class="stat-title">Venues</div>
                <div class="stat-value">{{ $venueCount }}</div>
            </div>
        </a>
        <a class="stat-card" href="{{ route('check-in') }}">
            <div class="stat-strip">Door</div>
            <div class="stat-body">
                <div class="stat-title">Check-in</div>
                <div class="stat-value">Open</div>
            </div>
        </a>
        <a class="stat-card" href="{{ route('reports') }}">
            <div class="stat-strip">Money</div>
            <div class="stat-body">
                <div class="stat-title">Reports</div>
                <div class="stat-value">View</div>
            </div>
        </a>
    </div>
    <section class="panel">
        <h2>Payments</h2>
        <p>Free registration is open. Paid checkout uses Authorize.net after sandbox credentials are saved under Payments. Stripe is not connected.</p>
    </section>
@endsection
