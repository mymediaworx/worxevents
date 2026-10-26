@extends('layouts.guest')

@section('content')
    <h1>Verify your email</h1>
    @if (session('status'))
        <p class="status" role="status">{{ session('status') }}</p>
    @endif
    <p>Use the link we sent before opening the organization.</p>
    <form method="post" action="{{ route('verification.send') }}">
        @csrf
        <button class="button" type="submit">Resend verification email</button>
    </form>
@endsection
