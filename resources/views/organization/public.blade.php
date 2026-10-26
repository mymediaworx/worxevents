@extends('layouts.guest')

@section('content')
    <p class="eyebrow">Organization</p>
    <h1>{{ $organization->name }}</h1>
    <p class="lede">Public event pages arrive in Phase 2. Staff and buyers sign in on this address. An account from another organization cannot sign in here.</p>
    <p><a class="button" href="{{ route('login') }}">Sign in</a> <a class="button button-quiet" href="{{ route('register') }}">Create a buyer account</a></p>
@endsection
