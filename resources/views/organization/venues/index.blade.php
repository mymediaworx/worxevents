@extends('layouts.app')

@section('content')
    <h1>Venues</h1>
    @can('events.manage')
        <p><a class="button" href="{{ route('venues.create') }}">New venue</a></p>
    @endcan
    @if ($venues->isEmpty())
        <p>No venues yet.</p>
    @else
        <ul>
            @foreach ($venues as $venue)
                <li>
                    {{ $venue->name }}
                    @if ($venue->city)
                        · {{ $venue->city }}
                    @endif
                    @can('events.manage')
                        <a href="{{ route('venues.edit', $venue) }}">Edit</a>
                    @endcan
                </li>
            @endforeach
        </ul>
    @endif
@endsection
