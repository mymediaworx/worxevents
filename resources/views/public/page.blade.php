@extends('layouts.guest')

@section('content')
    <h1>{{ $title }}</h1>
    @include('partials.errors')
    <p class="lede">{{ $body }}</p>
    @if (!empty($contact))
        <form method="post" action="{{ route('contact.store') }}" class="stack">
            @csrf
            <label>Name
                <input name="name" required autocomplete="name" value="{{ old('name') }}">
            </label>
            <label>Email
                <input name="email" type="email" required autocomplete="email" value="{{ old('email') }}">
            </label>
            <label>Message
                <textarea name="body" required>{{ old('body') }}</textarea>
            </label>
            <button class="button" type="submit">Send</button>
        </form>
    @endif
@endsection
