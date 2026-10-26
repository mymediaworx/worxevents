<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title ?? 'WorxEvents' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/worxevents.css') }}?v={{ filemtime(public_path('css/worxevents.css')) }}">
</head>
<body class="app">
    <a class="skip" href="#content">Skip to content</a>
    <div class="app-shell">
        <aside class="app-side" aria-label="Workspace">
            <a class="side-identity" href="{{ route('dashboard') }}">
                <span class="avatar" aria-hidden="true">{{ strtoupper(substr($currentOrganization->name ?? 'W', 0, 1)) }}</span>
                <span class="identity-text">
                    <span class="identity-name">{{ $currentOrganization->name ?? 'WorxEvents' }}</span>
                    <span class="identity-sub">Workspace</span>
                </span>
            </a>
            <nav class="side-nav" aria-label="Organization">
                @foreach ($navLinks as $link)
                    <a href="{{ $link['href'] }}" @class(['is-active' => request()->fullUrlIs($link['href'])])>{{ $link['label'] }}</a>
                @endforeach
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="nav-link">Sign out</button>
                </form>
            </nav>
        </aside>
        <div class="app-main">
            <header class="app-bar">
                <a class="app-identity" href="{{ route('dashboard') }}">
                    <span class="avatar" aria-hidden="true">{{ strtoupper(substr($currentOrganization->name ?? 'W', 0, 1)) }}</span>
                    <span class="identity-text">
                        <span class="identity-name">{{ $currentOrganization->name ?? 'WorxEvents' }}</span>
                        <span class="identity-sub">Workspace</span>
                </span>
            </a>
            <div class="app-bar-actions">
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="linkish">Sign out</button>
                </form>
            </div>
        </header>
            <main id="content" class="app-content">
                @if (session('status'))
                    <p class="status" role="status">{{ session('status') }}</p>
                @endif
                @if ($workspaces !== [])
                    <p class="muted">Other organizations:
                        @foreach ($workspaces as $workspace)
                            <a href="{{ $workspace['href'] }}">{{ $workspace['name'] }}</a>
                        @endforeach
                    </p>
                @endif
                @yield('content')
            </main>
        </div>
    </div>
    <nav class="tab-bar" aria-label="Organization">
        @foreach (array_slice($navLinks, 0, 4) as $link)
            <a href="{{ $link['href'] }}" @class(['is-active' => request()->fullUrlIs($link['href'])])>
                <span class="tab-icon">{{ strtoupper(substr($link['label'], 0, 1)) }}</span>
                <span class="tab-label">{{ $link['label'] }}</span>
            </a>
        @endforeach
    </nav>
    @include('partials.analytics')
    <script src="{{ asset('js/worxevents.js') }}?v={{ filemtime(public_path('js/worxevents.js')) }}" defer></script>
</body>
</html>
