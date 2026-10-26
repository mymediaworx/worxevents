@extends('layouts.guest')

@section('content')
    <h1>Reset password</h1>
    @if (! empty($status))
        <p class="status" role="status">{{ $status }}</p>
    @endif
    @include('partials.errors')
    <form method="post" action="{{ route('password.email') }}" class="stack">
        @csrf
        <label for="email">Email</label>
        <input id="email" name="email" type="email" autocomplete="email" required>
        <button class="button" type="submit">Email a reset link</button>
    </form>
@endsection
