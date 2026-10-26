<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title ?? 'WorxEvents platform' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/worxevents.css') }}?v={{ filemtime(public_path('css/worxevents.css')) }}">
</head>
<body class="app">
    <a class="skip" href="#content">Skip to content</a>
    @php
        $platformLinks = [
            ['label' => 'Organizations', 'href' => route('platform.dashboard')],
            ['label' => 'Users', 'href' => route('platform.users')],
            ['label' => 'Fee rules', 'href' => route('platform.fees')],
            ['label' => 'Plans', 'href' => route('platform.plans')],
            ['label' => 'Payments', 'href' => route('platform.payments')],
            ['label' => 'Disputes', 'href' => route('platform.disputes')],
            ['label' => 'Support', 'href' => route('platform.support')],
            ['label' => 'Audit', 'href' => route('platform.audit')],
            ['label' => 'Jobs', 'href' => route('platform.jobs')],
            ['label' => 'Security', 'href' => route('platform.security')],
            ['label' => 'Health', 'href' => route('platform.health')],
        ];
    @endphp
    <div class="app-shell">
        <aside class="app-side" aria-label="Platform">
            <a class="side-identity" href="{{ route('platform.dashboard') }}">
                <span class="avatar" aria-hidden="true">W</span>
                <span class="identity-text">
                    <span class="identity-name">WorxEvents</span>
                    <span class="identity-sub">Platform</span>
                </span>
            </a>
            <nav class="side-nav" aria-label="Platform">
                @foreach ($platformLinks as $link)
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
                <a class="app-identity" href="{{ route('platform.dashboard') }}">
                    <span class="avatar" aria-hidden="true">W</span>
                    <span class="identity-text">
                        <span class="identity-name">WorxEvents</span>
                        <span class="identity-sub">Platform</span>
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
                @yield('content')
            </main>
        </div>
    </div>
    <script src="{{ asset('js/worxevents.js') }}?v={{ filemtime(public_path('js/worxevents.js')) }}" defer></script>
</body>
</html>
