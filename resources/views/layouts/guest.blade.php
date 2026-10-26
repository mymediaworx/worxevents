<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'WorxEvents' }}</title>
    <link rel="stylesheet" href="{{ asset('css/worxevents.css') }}">
</head>
<body>
    <a class="skip" href="#content">Skip to content</a>
    <header class="site-header">
        <div class="wrap header-row">
            <a class="brand" href="{{ route('home') }}">WorxEvents</a>
            <nav class="nav" aria-label="Primary">
                <a href="{{ route('design') }}">Design</a>
                @auth
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="linkish">Sign out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}">Sign in</a>
                @endauth
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
