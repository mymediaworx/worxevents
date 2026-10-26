@extends('layouts.platform')

@section('content')
    <h1>Health</h1>
    <p>database {{ $database }}</p>
    <p>Failed jobs: {{ $failedJobs }}</p>
@endsection
