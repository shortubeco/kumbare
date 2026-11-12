<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') | Kumbare Yönetim</title>
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#0e3b3a">
    <link rel="preload" href="/fonts/bricolage-grotesque-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/source-sans-3-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/css/panel.css?v={{ @filemtime(public_path('css/panel.css')) }}">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
</head>
<body>
<a class="skip" href="#icerik">İçeriğe geç</a>
@php
    $navCounts = [
        'applications' => \Illuminate\Support\Facades\DB::table('businesses')->where('status', 'pending')->count(),
        'payments' => \Illuminate\Support\Facades\DB::table('payment_notifications')->where('status', 'pending')->count(),
        'reports' => \Illuminate\Support\Facades\DB::table('review_reports')->where('status', 'open')->distinct()->count('review_id'),
    ];
    $me = auth('web')->user();
    $nav = [
        ['Genel', [
            ['admin.dashboard', 'Genel bakış', null, ['admin.dashboard']],
        ]],
        ['İşletmeler', [
            ['admin.businesses', 'Başvurular', $navCounts['applications'], [], ['durum' => 'pending']],
            ['admin.businesses', 'İşletmeler ve şubeler', null, ['admin.businesses.show']],
            ['admin.customers', 'Müşteriler', null, ['admin.customers*']],
        ]],
        ['Ödemeler', [
            ['admin.payments', 'Havale bildirimleri', $navCounts['payments'], ['admin.payments*']],
            ['admin.subscriptions', 'Abonelikler', null, ['admin.subscriptions*']],
            ['admin.sponsorships', 'Sponsorluklar', null, ['admin.sponsorships*']],
        ]],
        ['İçerik', [
            ['admin.reports', 'Yorum şikâyetleri', $navCounts['reports'], ['admin.reports']],
            ['admin.campaigns', 'Kampanyalar', null, ['admin.campaigns*']],
            ['admin.categories', 'Kategoriler', null, ['admin.categories*']],
        ]],
        ['Sistem', [
            ['admin.settings', 'Paketler ve ayarlar', null, ['admin.settings*']],
            ['admin.audit', 'Denetim kayıtları', null, ['admin.audit*']],
        ]],
    ];
    $isPendingTab = request()->routeIs('admin.businesses') && request()->query('durum') === 'pending';
@endphp
<div class="p-shell">
    <aside class="p-side" aria-label="Yönetim menüsü">
        <div class="p-top">
            <a class="p-brand" href="{{ route('admin.dashboard') }}">
                @include('site.partials.logo')
                <span><b>kumbare</b><small>Platform yönetimi</small></span>
            </a>
            <button class="p-menu-toggle" type="button" aria-expanded="false" aria-controls="p-menu">Menü</button>
        </div>
        <div class="p-collapsible" id="p-menu">
            <nav class="p-nav">
                @foreach ($nav as [$group, $items])
                    <div class="grp">{{ $group }}</div>
                    @foreach ($items as $item)
                        @php
                            [$route, $label, $count, $patterns] = $item;
                            $params = $item[4] ?? [];
                            if ($route === 'admin.businesses') {
                                $active = $params ? $isPendingTab : ((request()->routeIs('admin.businesses') && ! $isPendingTab) || request()->routeIs('admin.businesses.show'));
                            } else {
                                $active = request()->routeIs(...$patterns);
                            }
                        @endphp
                        <a href="{{ route($route, $params) }}" @if ($active) aria-current="page" @endif>
                            <span>{{ $label }}</span>
                            @if ($count)<span class="count" aria-label="{{ $count }} bekleyen">{{ $count }}</span>@endif
                        </a>
                    @endforeach
                @endforeach
            </nav>
            <div class="p-user">
                <b>{{ $me->fullName() }}</b>
                {{ $me->email }}
                <form method="post" action="{{ route('panel.logout') }}">
                    @csrf
                    <button type="submit">Çıkış yap</button>
                </form>
            </div>
        </div>
    </aside>

    <main class="p-main" id="icerik">
        @if (session('status'))
            <div class="flash ok" role="status">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="flash err" role="alert">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="flash err" role="alert">
                Lütfen işaretli alanları kontrol edin:
                <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        @yield('content')
    </main>
</div>
<script src="/js/panel.js?v={{ @filemtime(public_path('js/panel.js')) }}" defer></script>
</body>
</html>
