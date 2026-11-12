<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Panel girişi') | Kumbare</title>
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#0e3b3a">
    <link rel="preload" href="/fonts/bricolage-grotesque-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/css/panel.css?v={{ @filemtime(public_path('css/panel.css')) }}">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
</head>
<body>
<main class="auth">
    <div class="auth-card">
        <a class="p-brand" href="{{ route('web.home') }}">
            @include('site.partials.logo')
            <span><b>kumbare</b><small>Panel</small></span>
        </a>
        @if (session('status'))
            <div class="flash ok" role="status">{{ session('status') }}</div>
        @endif
        @yield('content')
    </div>
</main>
<script src="/js/panel.js?v={{ @filemtime(public_path('js/panel.js')) }}" defer></script>
</body>
</html>
