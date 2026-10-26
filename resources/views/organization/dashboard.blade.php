@extends('layouts.app')

@section('content')
    <h1>Dashboard</h1>
    <p class="lede">Signed in as {{ auth()->user()->name }} · {{ $membership->role->name ?? 'Member' }}.</p>
    <p>Events, checkout, and check-in are later phases. Permissions on the pages in this menu are already enforced.</p>
@endsection
