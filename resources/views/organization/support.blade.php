@extends('layouts.app')

@section('content')
    <h1>Support</h1>
    @if (! empty($status))
        <p class="status" role="status">{{ $status }}</p>
    @endif
    <ul>
        @forelse ($cases as $case)
            <li>{{ $case->subject }} · {{ $case->status }}</li>
        @empty
            <li>No cases.</li>
        @endforelse
    </ul>
    <form method="post" action="{{ route('support.store') }}" class="stack">
        @csrf
        <label>Subject <input name="subject" required maxlength="160"></label>
        <label>What happened <textarea name="body" required></textarea></label>
        <button class="button" type="submit">Open case</button>
    </form>
@endsection
