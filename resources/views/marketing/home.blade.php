@extends('layouts.guest')

@section('content')
    <section class="hero">
        <p class="eyebrow">Event registration</p>
        <h1>Ticketing for organizers who keep the customer relationship.</h1>
        <p class="lede">WorxEvents is a multi-organization registration platform. Each organizer signs in on their own address, prices tickets on the server, and keeps their own payment account.</p>
        <p class="actions"><a class="button" href="{{ route('browse') }}">Browse events</a></p>
    </section>
    <section>
        <div class="page-head">
            <h2>Upcoming events</h2>
        </div>
        @if ($events->isEmpty())
            <p class="empty">Published events will appear here.</p>
        @else
            <ul class="cards">
                @foreach ($events as $event)
                    @include('partials.event-card', ['event' => $event])
                @endforeach
            </ul>
        @endif
    </section>
@endsection
