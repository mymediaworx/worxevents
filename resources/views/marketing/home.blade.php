@extends('layouts.guest')

@section('content')
    <section class="hero">
        <p class="eyebrow">Event registration</p>
        <h1>Ticketing for organizers who keep the customer relationship.</h1>
        <p class="lede">WorxEvents is a multi-organization registration platform. Each organizer signs in on their own address, prices tickets on the server, and keeps their own payment account.</p>
        <p><a class="button" href="{{ route('browse') }}">Browse events</a></p>
    </section>
    <section>
        <h2>Upcoming events</h2>
        @if ($events->isEmpty())
            <p>Published events will appear here.</p>
        @else
            <ul class="cards">
                @foreach ($events as $event)
                    <li class="card">
                        <h3><a href="{{ route('public.platform.event', ['organization' => $event->organization->slug, 'event' => $event->slug]) }}">{{ $event->title }}</a></h3>
                        <p class="meta">{{ $event->organization->name }} · {{ $event->starts_at->timezone($event->timezone)->format('M j, Y g:i A') }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
