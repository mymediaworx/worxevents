@extends('layouts.app')

@section('content')
    <h1>Promo QR</h1>
    <p>This code opens {{ $event->title }}.</p>
    <p><a href="{{ $url }}">{{ $url }}</a></p>
    {!! $svg !!}
@endsection
