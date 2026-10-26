@extends('layouts.guest')

@section('content')
    <h1>Access denied</h1>
    <p>{{ $exception->getMessage() ?: 'You do not have access to this page.' }}</p>
@endsection
