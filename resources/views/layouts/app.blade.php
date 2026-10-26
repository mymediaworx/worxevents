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
            <a class="brand" href="{{ route('dashboard') }}">{{ $currentOrganization->name ?? 'WorxEvents' }}</a>
            <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="app-nav">Menu</button>
            <nav id="app-nav" class="nav" aria-label="Organization">
                @foreach ($navLinks as $link)
                    <a href="{{ $link['href'] }}">{{ $link['label'] }}</a>
                @endforeach
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
        @if ($workspaces !== [])
            <p class="workspaces">Other organizations:
                @foreach ($workspaces as $workspace)
                    <a href="{{ $workspace['href'] }}">{{ $workspace['name'] }}</a>
                @endforeach
            </p>
        @endif
        @yield('content')
    </main>
    <script src="{{ asset('js/worxevents.js') }}" defer></script>
</body>
</html>
