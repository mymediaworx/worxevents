<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
    @if (!empty($accent))
        <style>:root { --accent: {{ $accent }}; }</style>
    @endif
    <link rel="stylesheet" href="{{ asset('css/worxevents.css') }}">
</head>
<body>
    <a class="skip" href="#content">Skip to content</a>
    <header class="site-header">
        <div class="wrap header-row">
            <a class="brand" href="{{ route('home') }}">{{ $currentOrganization->name ?? 'WorxEvents' }}</a>
            <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav">Menu</button>
            <nav id="site-nav" class="nav" aria-label="Primary">
                <a href="{{ route('browse') }}">Browse</a>
                <a href="{{ route('about') }}">About</a>
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
        </div>
    </header>
    <main id="content" class="wrap">
        @if (session('status'))
            <p class="status" role="status">{{ session('status') }}</p>
        @endif
        @yield('content')
    </main>
    <footer class="wrap footer-nav">
        <a href="{{ route('legal.terms') }}">Terms</a>
        <a href="{{ route('legal.privacy') }}">Privacy</a>
        <a href="{{ route('legal.refunds') }}">Refunds</a>
        <a href="{{ route('accessibility') }}">Accessibility</a>
        <a href="{{ route('sitemap') }}">Sitemap</a>
        <a href="{{ route('design') }}">Design</a>
    </footer>
    @include('partials.analytics')
    <script src="{{ asset('js/worxevents.js') }}" defer></script>
</body>
</html>
