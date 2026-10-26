@extends('layouts.guest')

@section('content')
    <h1>Choose a new password</h1>
    @include('partials.errors')
    <form method="post" action="{{ route('password.update') }}" class="stack">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" autocomplete="email" required value="{{ $email }}">
        <label for="password">New password</label>
        <input id="password" name="password" type="password" autocomplete="new-password" required>
        <label for="password_confirmation">Confirm password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
        <button class="button" type="submit">Update password</button>
    </form>
@endsection
