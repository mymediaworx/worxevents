@extends('layouts.app')

@section('content')
    <h1>Announcements</h1>
    <p>Mail goes only to addresses that opted in and have not unsubscribed.</p>
    @if (! empty($status))
        <p class="status" role="status">{{ $status }}</p>
    @endif
    <h2>Consent</h2>
    <ul>
        @forelse ($consents as $consent)
            <li>{{ $consent->email }} · {{ $consent->unsubscribed_at ? 'unsubscribed' : 'consented' }}</li>
        @empty
            <li>Nobody has opted in.</li>
        @endforelse
    </ul>
    <form method="post" action="{{ route('announcements.send') }}" class="stack">
        @csrf
        <label>Subject <input name="subject" required maxlength="160"></label>
        <label>Message <textarea name="body" required></textarea></label>
        <button class="button" type="submit">Send to consented addresses</button>
    </form>
@endsection
