@extends('layouts.guest')

@section('content')
    <p class="eyebrow">Venue</p>
    <h1>{{ $venue->name }}</h1>
    @if ($venue->addressLine() !== '')
        <p>{{ $venue->addressLine() }}</p>
    @endif
    @if ($venue->capacity)
        <p class="meta">Capacity {{ $venue->capacity }}</p>
    @endif
    <h2>Events here</h2>
    @if ($events->isEmpty())
        <p>No published events at this venue.</p>
    @else
        <ul>
            @foreach ($events as $event)
                <li><a href="{{ route('public.platform.event', ['organization' => $venue->organization->slug, 'event' => $event->slug]) }}">{{ $event->title }}</a></li>
            @endforeach
        </ul>
    @endif
@endsection
