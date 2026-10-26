@extends('layouts.guest')

@section('content')
    <h1>Create a buyer account</h1>
    @include('partials.errors')
    <form method="post" action="{{ route('register') }}" class="stack">
        @csrf
        <label for="name">Name</label>
        <input id="name" name="name" autocomplete="name" required>
        <label for="email">Email</label>
        <input id="email" name="email" type="email" autocomplete="email" required>
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="new-password" required>
        <label for="password_confirmation">Confirm password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
        <button class="button" type="submit">Create account</button>
    </form>
@endsection
