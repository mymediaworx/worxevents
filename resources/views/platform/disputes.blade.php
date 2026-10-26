@extends('layouts.platform')

@section('content')
    <h1>Disputes</h1>
    @if (! empty($status))
        <p class="status" role="status">{{ $status }}</p>
    @endif
    @if (! empty($message))
        <p class="alert" role="alert">{{ $message }}</p>
    @endif
    <ul>
        @forelse ($disputes as $dispute)
            <li>
                {{ $dispute->summary }} · {{ $dispute->status }}
                @if ($dispute->organization)
                    · {{ $dispute->organization->name }}
                @endif
                @can('platform.organizations.manage')
                    <form method="post" action="{{ route('platform.disputes.status', $dispute) }}">
                        @csrf
                        <label>Status
                            <select name="status">
                                <option value="open">Open</option>
                                <option value="won">Won</option>
                                <option value="lost">Lost</option>
                            </select>
                        </label>
                        <button type="submit">Update</button>
                    </form>
                @endcan
            </li>
        @empty
            <li>No disputes.</li>
        @endforelse
    </ul>
    @can('platform.organizations.manage')
        <form method="post" action="{{ route('platform.disputes.store') }}" class="stack">
            @csrf
            <label>Organization
                <select name="for_organization">
                    <option value="">None</option>
                    @foreach ($organizations as $organization)
                        <option value="{{ $organization->id }}">{{ $organization->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Summary <input name="summary" required maxlength="500"></label>
            <button class="button" type="submit">Open dispute</button>
        </form>
    @endcan
@endsection
