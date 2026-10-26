@extends('layouts.app')

@section('content')
    <h1>Team</h1>
    @include('partials.errors')
    @if (! empty($message))
        <p class="alert" role="alert">{{ $message }}</p>
    @endif
    <livewire:team-roster />

    @can('organization.members.manage')
        <h2>Invite</h2>
        <form method="post" action="{{ route('team.invite') }}" class="stack">
            @csrf
            <label for="email">Email</label>
            <input id="email" name="email" type="email" autocomplete="email" required>
            <label for="role">Role</label>
            <select id="role" name="role" required>
                @foreach ($roles as $role)
                    <option value="{{ $role->slug }}">{{ $role->name }}</option>
                @endforeach
            </select>
            <button class="button" type="submit">Send invitation</button>
        </form>
    @endcan

    @if ($invitations->isNotEmpty())
        <h2>Open invitations</h2>
        <ul>
            @foreach ($invitations as $invitation)
                <li>{{ $invitation->email }} · expires {{ $invitation->expires_at->timezone('UTC')->toDateString() }} UTC</li>
            @endforeach
        </ul>
    @endif
@endsection
