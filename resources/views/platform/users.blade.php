@extends('layouts.platform')

@section('content')
    <h1>Users and roles</h1>
    <ul>
        @foreach ($users as $user)
            <li>
                {{ $user->email }}
                @if ($user->platformMembership)
                    · platform {{ $user->platformMembership->role->slug }}
                @endif
                @foreach ($user->memberships as $membership)
                    · {{ $membership->organization->name }} {{ $membership->role->slug }}
                @endforeach
            </li>
        @endforeach
    </ul>
@endsection
