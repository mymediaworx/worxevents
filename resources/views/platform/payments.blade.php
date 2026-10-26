@extends('layouts.platform')

@section('content')
    <h1>Payment oversight</h1>
    <p>Credentials stay encrypted. This list shows the environment and whether the connection was validated.</p>
    <ul>
        @forelse ($settings as $setting)
            <li>{{ $setting->organization->name }} · {{ $setting->environment }} · {{ $setting->validated_at ? 'validated' : 'not validated' }}</li>
        @empty
            <li>No payment settings.</li>
        @endforelse
    </ul>
@endsection
