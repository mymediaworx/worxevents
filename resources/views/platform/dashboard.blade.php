@extends('layouts.platform')

@section('content')
    <h1>Organizations</h1>
    @if (! empty($status))
        <p class="status" role="status">{{ $status }}</p>
    @endif
    <ul data-organization-count="{{ $organizations->count() }}">
        @foreach ($organizations as $organization)
            <li>
                {{ $organization->name }} · {{ $organization->status->value }}
                @can('platform.organizations.manage')
                    <form method="post" action="{{ route('platform.organizations.status', $organization) }}">
                        @csrf
                        <input type="hidden" name="status" value="{{ $organization->status->value === 'suspended' ? 'active' : 'suspended' }}">
                        <button type="submit">{{ $organization->status->value === 'suspended' ? 'Reinstate' : 'Suspend' }}</button>
                    </form>
                @endcan
            </li>
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
