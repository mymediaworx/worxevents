<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title ?? 'WorxEvents' }}</title>
    <meta name="description" content="{{ $description ?? 'WorxEvents registration for organizers and attendees.' }}">
    @isset($robots)
        <meta name="robots" content="{{ $robots }}">
    @endisset
    <meta property="og:title" content="{{ $ogTitle ?? ($title ?? 'WorxEvents') }}">
    <meta property="og:description" content="{{ $ogDescription ?? ($description ?? 'WorxEvents registration for organizers and attendees.') }}">
    <meta property="og:type" content="{{ $ogType ?? 'website' }}">
    @isset($ogUrl)
        <meta property="og:url" content="{{ $ogUrl }}">
    @endisset
    @isset($ogImage)
        <meta property="og:image" content="{{ $ogImage }}">
    @endisset
    @isset($jsonLd)
        <script type="application/ld+json">@json($jsonLd)</script>
    @endisset
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/worxevents.css') }}?v={{ filemtime(public_path('css/worxevents.css')) }}">
</head>
@hasSection('bare')
<body class="auth">
    <a class="skip" href="#content">Skip to content</a>
    <main id="content">
        @yield('content')
    </main>
@else
<body class="public">
    <a class="skip" href="#content">Skip to content</a>
    <header class="site-header">
        <a class="brand" href="{{ route('home') }}">
            <span class="avatar" aria-hidden="true">W</span>
            <span class="identity-text">
                <span class="identity-name">{{ $currentOrganization->name ?? 'WorxEvents' }}</span>
                <span class="identity-sub">Events</span>
            </span>
        </a>
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav">Menu</button>
        <nav id="site-nav" class="nav" aria-label="Primary">
            <a href="{{ route('browse') }}">Browse</a>
            <a href="{{ route('pricing') }}">Pricing</a>
            <a href="{{ route('help') }}">Help</a>
            <a href="{{ route('contact') }}">Contact</a>
            @auth
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="linkish">Sign out</button>
                </form>
            @else
                <a href="{{ route('login') }}">Sign in</a>
            @endauth
        </nav>
    </header>
    <main id="content" class="public-main">
        @if (session('status'))
            <p class="status" role="status">{{ session('status') }}</p>
        @endif
        @yield('content')
    </main>
    <footer class="site-footer">
        <div class="wrap">
            <span>WorxEvents</span>
            <nav aria-label="Footer">
                <a href="{{ route('legal.terms') }}">Terms</a>
                <a href="{{ route('legal.privacy') }}">Privacy</a>
                <a href="{{ route('legal.refunds') }}">Refunds</a>
                <a href="{{ route('accessibility') }}">Accessibility</a>
                <a href="{{ route('contact') }}">Contact</a>
            </nav>
        </div>
    </footer>
@endif
    @include('partials.analytics')
    <script src="{{ asset('js/worxevents.js') }}?v={{ filemtime(public_path('js/worxevents.js')) }}" defer></script>
</body>
</html>
