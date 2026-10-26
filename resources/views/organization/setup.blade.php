@extends('layouts.app')

@section('content')
    <h1>Organization setup</h1>
    <p class="lede">Step {{ $organization->setup_step }} of 3. Each section saves a draft.</p>
    @include('partials.errors')
    <h2>Details</h2>
    <form method="post" action="{{ route('setup.details') }}" class="stack">
        @csrf
        <label>Legal name
            <input name="legal_name" required value="{{ old('legal_name', $organization->legal_name) }}">
        </label>
        <label>Support email
            <input name="support_email" type="email" required value="{{ old('support_email', $organization->support_email) }}">
        </label>
        <label>Public description
            <textarea name="description">{{ old('description', $organization->description) }}</textarea>
        </label>
        <button class="button" type="submit">Save details</button>
    </form>
    <h2>Branding</h2>
    <form method="post" action="{{ route('setup.branding') }}" class="stack" enctype="multipart/form-data">
        @csrf
        <label>Accent color
            <input name="brand_accent" value="{{ old('brand_accent', $organization->brand_accent) }}" placeholder="#0c5c4e" pattern="#[0-9A-Fa-f]{6}">
        </label>
        <label>Logo
            <input name="logo" type="file" accept="image/png,image/jpeg,image/webp,image/gif">
        </label>
        <button class="button" type="submit">Save branding</button>
    </form>
    <h2>Currency and time zone</h2>
    <form method="post" action="{{ route('setup.locale') }}" class="stack">
        @csrf
        <label>Currency
            <input name="currency" value="{{ old('currency', $organization->currency ?: 'USD') }}" required>
        </label>
        <p class="meta">USD is the only currency in this release.</p>
        <label>Time zone
            <input name="timezone" value="{{ old('timezone', $organization->timezone) }}" required placeholder="America/New_York">
        </label>
        <button class="button" type="submit">Save locale</button>
    </form>
    <h2>Finish</h2>
    <form method="post" action="{{ route('setup.finish') }}">
        @csrf
        <button class="button" type="submit">Mark setup complete</button>
    </form>
@endsection
