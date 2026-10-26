@extends('layouts.app')

@section('content')
    <h1>{{ $event ? 'Edit '.$event->title : 'New event' }}</h1>
    @include('partials.errors')
    <form method="post" action="{{ $event ? route('events.update', $event) : route('events.store') }}" class="stack" enctype="multipart/form-data">
        @csrf
        <label>Title
            <input name="title" required value="{{ old('title', $event?->title) }}">
        </label>
        <label>Summary
            <input name="summary" maxlength="300" value="{{ old('summary', $event?->summary) }}">
        </label>
        <label>Description
            <textarea name="description">{{ old('description', $event?->description) }}</textarea>
        </label>
        <label>Time zone
            <input name="timezone" required value="{{ old('timezone', $event?->timezone ?? $organization->timezone) }}">
        </label>
        <label>Starts
            <input name="starts_at" type="datetime-local" required value="{{ old('starts_at', isset($event) ? $event->starts_at->timezone($event->timezone)->format('Y-m-d\TH:i') : '') }}">
        </label>
        <label>Ends
            <input name="ends_at" type="datetime-local" required value="{{ old('ends_at', isset($event) ? $event->ends_at->timezone($event->timezone)->format('Y-m-d\TH:i') : '') }}">
        </label>
        <label>Multi-day
            <input type="checkbox" name="is_multi_day" value="1" @checked(old('is_multi_day', $event?->is_multi_day ?? false))>
        </label>
        <label>Venue
            <select name="venue_id">
                <option value="">None</option>
                @foreach ($venues as $venue)
                    <option value="{{ $venue->id }}" @selected(old('venue_id', $event?->venue_id ?? '') === $venue->id)>{{ $venue->name }}</option>
                @endforeach
            </select>
        </label>
        <label>Category
            <input name="category_name" value="{{ old('category_name', $event?->category?->name) }}">
        </label>
        <label>Tags
            <input name="tags" value="{{ old('tags', isset($event) ? $event->tags->pluck('name')->implode(', ') : '') }}">
        </label>
        <label>Capacity
            <input name="capacity" type="number" min="1" value="{{ old('capacity', $event?->capacity) }}">
        </label>
        <label>Sales start
            <input name="sales_starts_at" type="datetime-local" value="{{ old('sales_starts_at', isset($event) && $event->sales_starts_at ? $event->sales_starts_at->timezone($event->timezone)->format('Y-m-d\TH:i') : '') }}">
        </label>
        <label>Sales end
            <input name="sales_ends_at" type="datetime-local" value="{{ old('sales_ends_at', isset($event) && $event->sales_ends_at ? $event->sales_ends_at->timezone($event->timezone)->format('Y-m-d\TH:i') : '') }}">
        </label>
        <label>SEO title
            <input name="seo_title" value="{{ old('seo_title', $event?->seo_title) }}">
        </label>
        <label>SEO description
            <input name="seo_description" maxlength="320" value="{{ old('seo_description', $event?->seo_description) }}">
        </label>
        @if ($event)
            <label>Image
                <input name="image" type="file" accept="image/png,image/jpeg,image/webp,image/gif">
            </label>
        @endif
        <button class="button" type="submit">Save draft</button>
    </form>
    @if ($event)
        <h2>Publish and status</h2>
        <p>Current status: {{ $event->status->value }}</p>
        <form method="post" action="{{ route('events.publish', $event) }}">
            @csrf
            <button class="button" type="submit">Publish</button>
        </form>
        @foreach ($statuses as $status)
            @if ($status->value !== 'published')
                <form method="post" action="{{ route('events.status', $event) }}">
                    @csrf
                    <input type="hidden" name="status" value="{{ $status->value }}">
                    <button class="button button-quiet" type="submit">Mark {{ $status->value }}</button>
                </form>
            @endif
        @endforeach
        <p><a href="{{ route('events.extras', $event) }}">Series, discounts, seats, and waitlist</a></p>
        <h2>Sessions</h2>
        <ul>
            @foreach ($event->sessions as $session)
                <li>{{ $session->name }} · capacity {{ $session->capacity ?? 'open' }}</li>
            @endforeach
        </ul>
        <form method="post" action="{{ route('events.sessions.store', $event) }}" class="stack">
            @csrf
            <label>Session name <input name="name" required></label>
            <label>Starts <input name="starts_at" type="datetime-local" required></label>
            <label>Ends <input name="ends_at" type="datetime-local" required></label>
            <label>Capacity <input name="capacity" type="number" min="1"></label>
            <button class="button" type="submit">Add session</button>
        </form>
        <h2>Ticket types</h2>
        <ul>
            @foreach ($event->ticketTypes as $type)
                <li>{{ $type->name }} · {{ $type->kind->value }} · {{ $type->price_cents }} cents · {{ $type->quantity_sold }}/{{ $type->quantity_total }} sold · {{ $type->visibility->value }}</li>
            @endforeach
        </ul>
        <form method="post" action="{{ route('events.tickets.store', $event) }}" class="stack">
            @csrf
            <label>Name <input name="name" required></label>
            <label>Kind
                <select name="kind">
                    @foreach ($kinds as $kind)
                        <option value="{{ $kind->value }}">{{ $kind->label() }}</option>
                    @endforeach
                </select>
            </label>
            <label>Price in dollars <input name="price" value="0.00"></label>
            <label>Quantity <input name="quantity_total" type="number" min="0" value="100" required></label>
            <label>Admits (group tickets) <input name="admits" type="number" min="1" max="20" value="1"></label>
            <label>Minimum per order <input name="min_per_order" type="number" min="1" max="20" value="1" required></label>
            <label>Maximum per order <input name="max_per_order" type="number" min="1" max="20" value="10" required></label>
            <label>Visibility
                <select name="visibility">
                    @foreach ($visibilities as $visibility)
                        <option value="{{ $visibility->value }}">{{ $visibility->value }}</option>
                    @endforeach
                </select>
            </label>
            <label>Session
                <select name="session_id">
                    <option value="">Whole event</option>
                    @foreach ($event->sessions as $session)
                        <option value="{{ $session->id }}">{{ $session->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Sales start <input name="sales_starts_at" type="datetime-local"></label>
            <label>Sales end <input name="sales_ends_at" type="datetime-local"></label>
            <label>Description <textarea name="description"></textarea></label>
            <button class="button" type="submit">Add ticket type</button>
        </form>
        <h2>Questions</h2>
        <ul>
            @foreach ($event->questions as $question)
                <li>{{ $question->label }} · {{ $question->field_type->value }}@if ($question->required) · required @endif</li>
            @endforeach
        </ul>
        <form method="post" action="{{ route('events.questions.store', $event) }}" class="stack">
            @csrf
            <label>Label <input name="label" required></label>
            <label>Type
                <select name="field_type">
                    @foreach ($questionFields as $field)
                        <option value="{{ $field->value }}">{{ $field->value }}</option>
                    @endforeach
                </select>
            </label>
            <label>Required <input type="checkbox" name="required" value="1"></label>
            <label>Options, one per line <textarea name="options"></textarea></label>
            <button class="button" type="submit">Add question</button>
        </form>
    @endif
@endsection
