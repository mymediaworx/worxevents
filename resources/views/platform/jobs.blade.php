@extends('layouts.platform')

@section('content')
    <h1>Jobs</h1>
    <p>Queued jobs: {{ $queued }}</p>
    <h2>Failed jobs</h2>
    <ul>
        @forelse ($failed as $job)
            <li>{{ $job->uuid }} · {{ $job->queue }} · {{ $job->failed_at }}</li>
        @empty
            <li>No failed jobs.</li>
        @endforelse
    </ul>
@endsection
