@extends('layouts.platform')

@section('content')
    <h1>Audit log</h1>
    <ul>
        @forelse ($audits as $audit)
            <li>{{ $audit->action }} · {{ $audit->created_at->timezone('UTC')->toIso8601String() }}</li>
        @empty
            <li>No audit entries.</li>
        @endforelse
    </ul>
@endsection
