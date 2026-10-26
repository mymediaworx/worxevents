@extends('layouts.guest')

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $category ? 'Category' : 'Browse events' }}</h1>
            @if ($category)
                <p class="lede">Events filed under {{ $category }}.</p>
            @endif
        </div>
    </div>
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
        <p class="empty">No events match.</p>
    @else
        <ul class="cards">
            @foreach ($events as $event)
                @include('partials.event-card', ['event' => $event])
            @endforeach
        </ul>
        @if ($events->hasPages())
            <nav class="actions" aria-label="Pages">
                @if ($events->previousPageUrl())
                    <a class="button button-quiet" href="{{ $events->previousPageUrl() }}">Previous</a>
                @endif
                @if ($events->nextPageUrl())
                    <a class="button button-quiet" href="{{ $events->nextPageUrl() }}">Next</a>
                @endif
            </nav>
        @endif
    @endif
@endsection
