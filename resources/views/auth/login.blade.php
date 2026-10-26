@extends('layouts.guest')

@section('bare')
@endsection

@section('content')
    <div class="auth-panel">
    <div class="auth-brand">
        <span class="avatar" aria-hidden="true">W</span>
        <p class="auth-tenant">WorxEvents</p>
    </div>
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
    <p class="auth-foot"><a href="{{ route('password.request') }}">Forgot password?</a></p>
    </div>
@endsection
