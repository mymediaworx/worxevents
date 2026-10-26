@extends('layouts.platform')

@section('content')
    <h1>Support cases</h1>
    @if (! empty($status))
        <p class="status" role="status">{{ $status }}</p>
    @endif
    <ul>
        @forelse ($cases as $case)
            <li>
                {{ $case->organization->name }} · {{ $case->subject }} · {{ $case->status }}
                @if ($case->status === 'open' && auth()->user()->can('platform.organizations.manage'))
                    <form method="post" action="{{ route('platform.support.close', $case) }}">
                        @csrf
                        <button type="submit">Close</button>
                    </form>
                @endif
            </li>
        @empty
            <li>No cases.</li>
        @endforelse
    </ul>
@endsection
