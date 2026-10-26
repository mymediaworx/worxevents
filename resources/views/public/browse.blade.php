@extends('layouts.guest')

@section('content')
    <h1>{{ $category ? 'Category' : 'Browse events' }}</h1>
    @if ($category)
        <p class="lede">Events filed under {{ $category }}.</p>
    @endif
    <form method="get" action="{{ $category ? route('public.category', $category) : route('browse') }}" class="filters">
        <label>Search
            <input name="q" value="{{ $filters['q'] }}" type="search">
        </label>
        <label>City
            <input name="city" value="{{ $filters['city'] }}">
        </label>
        <label>Tag
            <input name="tag" value="{{ $filters['tag'] }}">
        </label>
        <label>From
            <input name="from" type="date" value="{{ $filters['from'] }}">
        </label>
        <label>To
            <input name="to" type="date" value="{{ $filters['to'] }}">
        </label>
        <button class="button" type="submit">Filter</button>
    </form>
    @if ($events->isEmpty())
        <p>No events match.</p>
    @else
        <ul class="cards">
            @foreach ($events as $event)
                <li class="card">
                    <h2><a href="{{ route('public.platform.event', ['organization' => $event->organization->slug, 'event' => $event->slug]) }}">{{ $event->title }}</a></h2>
                    <p class="meta">{{ $event->organization->name }} · {{ $event->starts_at->timezone($event->timezone)->format('M j, Y') }}
                        @if ($event->venue && $event->venue->city)
                            · {{ $event->venue->city }}
                        @endif
                    </p>
                </li>
            @endforeach
        </ul>
        @if ($events->hasPages())
            <nav aria-label="Pages">
                @if ($events->previousPageUrl())
                    <a href="{{ $events->previousPageUrl() }}">Previous</a>
                @endif
                @if ($events->nextPageUrl())
                    <a href="{{ $events->nextPageUrl() }}">Next</a>
                @endif
            </nav>
        @endif
    @endif
@endsection
