@extends('layouts.guest')

@section('content')
    <h1>Not found</h1>
    <p>{{ $exception->getMessage() ?: 'That page is not available.' }}</p>
@endsection
