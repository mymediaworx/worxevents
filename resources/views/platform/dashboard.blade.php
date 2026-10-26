@extends('layouts.platform')

@section('content')
    <h1>Organizations</h1>
    <ul data-organization-count="{{ $organizations->count() }}">
        @foreach ($organizations as $organization)
            <li>{{ $organization->name }} · {{ $organization->status->value }}</li>
        @endforeach
    </ul>
    <h2>Recent audit</h2>
    <ul>
        @forelse ($audits as $audit)
            <li>{{ $audit->action }} · {{ $audit->created_at->timezone('UTC')->toIso8601String() }}</li>
        @empty
            <li>No audit entries.</li>
        @endforelse
    </ul>
@endsection
