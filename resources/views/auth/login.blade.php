@extends('layouts.guest')

@section('content')
    <h1>Sign in</h1>
    @include('partials.errors')
    <form method="post" action="{{ route('login') }}" class="stack">
        @csrf
        <label for="email">Email</label>
        <input id="email" name="email" type="email" autocomplete="username" required>
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <button class="button" type="submit">Sign in</button>
    </form>
    <p><a href="{{ route('password.request') }}">Forgot password?</a></p>
@endsection
