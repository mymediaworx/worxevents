@extends('layouts.platform')

@section('content')
    <h1>Security events</h1>
    <ul>
        @forelse ($events as $event)
            <li>{{ $event->kind }} · {{ $event->message }}</li>
        @empty
            <li>No security events.</li>
        @endforelse
    </ul>
@endsection
