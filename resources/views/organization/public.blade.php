@extends('layouts.guest')

@section('content')
    <p class="eyebrow">Organization</p>
    <h1>{{ $organization->name }}</h1>
    @if ($organization->description)
        <p class="lede">{{ $organization->description }}</p>
    @else
        <p class="lede">Events from {{ $organization->name }}.</p>
    @endif
    @if ($organization->logo_path)
        <p><img src="{{ route('media.logo') }}" alt="" width="160" height="80"></p>
    @endif
    <p><a class="button" href="{{ route('login') }}">Sign in</a> <a class="button button-quiet" href="{{ route('register') }}">Create a buyer account</a></p>
    <h2>Events</h2>
    @if ($events->isEmpty())
        <p>No published events yet.</p>
    @else
        <ul class="cards">
            @foreach ($events as $event)
                <li class="card">
                    <h3><a href="{{ route('public.event', $event->slug) }}">{{ $event->title }}</a></h3>
                    <p class="meta">{{ $event->starts_at->timezone($event->timezone)->format('M j, Y g:i A') }}@if ($event->venue) · {{ $event->venue->city ?: $event->venue->name }}@endif</p>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
