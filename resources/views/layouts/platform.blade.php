<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'WorxEvents platform' }}</title>
    <link rel="stylesheet" href="{{ asset('css/worxevents.css') }}">
</head>
<body>
    <a class="skip" href="#content">Skip to content</a>
    <header class="site-header">
        <div class="wrap header-row">
            <a class="brand" href="{{ route('platform.dashboard') }}">WorxEvents</a>
            <nav class="nav" aria-label="Platform">
                <a href="{{ route('platform.dashboard') }}">Organizations</a>
                <a href="{{ route('platform.fees') }}">Fee rules</a>
                <a href="{{ route('design') }}">Design</a>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="linkish">Sign out</button>
                </form>
            </nav>
        </div>
    </header>
    <main id="content" class="wrap">
        @if (session('status'))
            <p class="status" role="status">{{ session('status') }}</p>
        @endif
        @yield('content')
    </main>
</body>
</html>
