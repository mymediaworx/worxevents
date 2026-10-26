@extends('layouts.guest')

@section('content')
    <h1>Invitation</h1>
    @if (! empty($message))
        <p class="alert" role="alert">{{ $message }}</p>
    @endif
    @if ($invitation)
        <p>Role: {{ $invitation->role->name ?? 'Member' }}.</p>
        @auth
            <form method="post" action="{{ route('invitations.accept', ['token' => $token]) }}">
                @csrf
                <button class="button" type="submit">Accept invitation</button>
            </form>
        @else
            <p><a href="{{ route('login') }}">Sign in</a> with the invited email, then open this page again.</p>
        @endauth
    @elseif (empty($message))
        <p>This invitation is not valid.</p>
    @endif
@endsection
