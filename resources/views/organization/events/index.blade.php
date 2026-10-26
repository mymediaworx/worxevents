@extends('layouts.app')

@section('content')
    <h1>Events</h1>
    @can('events.manage')
        <p><a class="button" href="{{ route('events.create') }}">New event</a></p>
    @endcan
    @if ($events->isEmpty())
        <p>No events yet. Drafts stay off the public site.</p>
    @else
        <table>
            <caption>Your events</caption>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Status</th>
                    <th>Starts</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($events as $event)
                    <tr>
                        <td>{{ $event->title }}</td>
                        <td>{{ $event->status->value }}</td>
                        <td>{{ $event->starts_at->timezone($event->timezone)->format('M j, Y g:i A') }}</td>
                        <td>
                            @can('events.manage')
                                <a href="{{ route('events.edit', $event) }}">Edit</a>
                            @endcan
                            <a href="{{ route('events.preview', $event) }}">Preview</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
