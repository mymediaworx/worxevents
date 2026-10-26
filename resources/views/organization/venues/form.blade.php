@extends('layouts.app')

@section('content')
    <h1>{{ $title }}</h1>
    @include('partials.errors')
    <form method="post" action="{{ $venue ? route('venues.update', $venue) : route('venues.store') }}" class="stack">
        @csrf
        <label>Name <input name="name" required value="{{ old('name', $venue?->name) }}"></label>
        <label>Address <input name="address_line" value="{{ old('address_line', $venue?->address_line) }}"></label>
        <label>City <input name="city" value="{{ old('city', $venue?->city) }}"></label>
        <label>Region <input name="region" value="{{ old('region', $venue?->region) }}"></label>
        <label>Postal code <input name="postal_code" value="{{ old('postal_code', $venue?->postal_code) }}"></label>
        <label>Country <input name="country" maxlength="2" value="{{ old('country', $venue?->country) }}"></label>
        <label>Capacity <input name="capacity" type="number" min="1" value="{{ old('capacity', $venue?->capacity) }}"></label>
        <button class="button" type="submit">Save venue</button>
    </form>
@endsection
