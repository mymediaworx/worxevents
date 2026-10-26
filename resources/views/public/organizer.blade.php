@extends('layouts.guest')

@section('content')
    <p class="eyebrow">Organizer</p>
    <h1>{{ $organization->name }}</h1>
    @if ($organization->description)
        <p class="lede">{{ $organization->description }}</p>
    @endif
    @if ($organization->support_email)
        <p>Support: <a href="mailto:{{ $organization->support_email }}">{{ $organization->support_email }}</a></p>
    @endif
    <h2>Published events</h2>
    @if ($events->isEmpty())
        <p>No published events yet.</p>
    @else
        <ul class="cards">
            @foreach ($events as $event)
                <li class="card">
                    <h3><a href="{{ route('public.platform.event', ['organization' => $organization->slug, 'event' => $event->slug]) }}">{{ $event->title }}</a></h3>
                    <p class="meta">{{ $event->starts_at->timezone($event->timezone)->format('M j, Y g:i A') }}</p>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
