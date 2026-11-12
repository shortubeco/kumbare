<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') | Kumbare Panel</title>
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#0e3b3a">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preload" href="/fonts/bricolage-grotesque-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/source-sans-3-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/css/panel.css?v={{ @filemtime(public_path('css/panel.css')) }}">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
</head>
<body>
<a class="skip" href="#icerik">İçeriğe geç</a>
@php
    $me = auth('web')->user();
    $myBranch = request()->attributes->get('branch') ?? $me->branch;
    $isSponsorForm = request()->routeIs('panel.payment.create') && request()->query('amac') === 'sponsorluk';
    $unreadCount = \App\Models\PanelNotification::query()->where('panel_user_id', $me->id)->whereNull('read_at')->count();
    // [rota, etiket, etkin mi, rozet sayısı]
    $groups = [
        ['Kasa', [
            ['panel.tx.screen', 'İşlem ekranı', request()->routeIs('panel.tx.*'), 0],
        ]],
    ];
    if ($me->hasPermission(\App\Models\PanelUser::PERM_VIEW_CUSTOMERS)) {
        $groups[0][1][] = ['panel.customers', 'Müşteriler', request()->routeIs('panel.customers*'), 0];
    }
    if ($me->hasPermission(\App\Models\PanelUser::PERM_VIEW_REPORTS)) {
        $groups[0][1][] = ['panel.reports', 'Raporlar', request()->routeIs('panel.reports*'), 0];
    }
    if ($me->isBranchManager()) {
        array_unshift($groups[0][1], ['panel.dashboard', 'Genel bakış', request()->routeIs('panel.dashboard'), 0]);
        $groups[0][1][] = ['panel.corrections', 'Düzeltmeler', request()->routeIs('panel.corrections*'), 0];
        $groups[0][0] = 'Şube';
        $unanswered = $myBranch ? \App\Models\Review::query()->where('branch_id', $myBranch->id)->where('status', 'published')->whereDoesntHave('reply')->count() : 0;
        $groups[] = ['Ayarlar', [
            ['panel.rate', 'Ödül oranı', request()->routeIs('panel.rate*'), 0],
            ['panel.campaigns', 'Kampanyalar', request()->routeIs('panel.campaigns*'), 0],
            ['panel.staff', 'Çalışanlar', request()->routeIs('panel.staff*'), 0],
            ['panel.info', 'Şube bilgileri', request()->routeIs('panel.info*') || request()->routeIs('panel.photos*'), 0],
            ['panel.reviews', 'Değerlendirmeler', request()->routeIs('panel.reviews*'), $unanswered],
        ]];
        $groups[] = ['Ödemeler', [
            ['panel.package', 'Paket ve ödeme', request()->routeIs('panel.package') || (request()->routeIs('panel.payment.create') && ! $isSponsorForm), 0],
            ['panel.sponsorship', 'Sponsorluk', request()->routeIs('panel.sponsorship') || $isSponsorForm, 0],
        ]];
    }
    $groups[] = ['Hesap', [
        ['panel.notifications', 'Bildirimler', request()->routeIs('panel.notifications*'), $unreadCount],
    ]];

    // İşletme (marka) yöneticisi: ayrı menü
    $isOwner = $me->role === \App\Models\PanelUser::ROLE_BUSINESS_OWNER;
    $myBusiness = $isOwner ? (request()->attributes->get('business') ?? $me->business) : null;
    if ($isOwner) {
        $groups = [
            ['İşletme', [
                ['biz.overview', 'Genel bakış', request()->routeIs('biz.overview'), 0],
                ['biz.branches', 'Şubeler', request()->routeIs('biz.branches*'), 0],
                ['biz.managers', 'Şube yöneticileri', request()->routeIs('biz.managers*'), 0],
                ['biz.menu', 'Menü', request()->routeIs('biz.menu*'), 0],
                ['biz.reports', 'Raporlar', request()->routeIs('biz.reports'), 0],
                ['biz.subscriptions', 'Abonelikler', request()->routeIs('biz.subscriptions'), 0],
            ]],
            ['Ayarlar', [
                ['biz.profile', 'İşletme bilgileri', request()->routeIs('biz.profile*'), 0],
            ]],
        ];
    }
@endphp
<div class="p-shell">
    <aside class="p-side" aria-label="Şube menüsü">
        <div class="p-top">
            <a class="p-brand" href="{{ route('panel.home') }}">
                @include('site.partials.logo')
                <span><b>kumbare</b><small>{{ $isOwner ? 'İşletme paneli' : 'Şube paneli' }}</small></span>
            </a>
            <button class="p-menu-toggle" type="button" aria-expanded="false" aria-controls="p-menu">Menü</button>
        </div>
        <div class="p-collapsible" id="p-menu">
            @if ($isOwner && $myBusiness)
                <div class="p-branch">
                    <b>{{ $myBusiness->name }}</b>
                    İşletme yöneticisi
                </div>
            @elseif ($myBranch)
                <div class="p-branch">
                    <b>{{ $myBranch->business?->name }}</b>
                    {{ $myBranch->name }} şubesi
                </div>
            @endif
            <nav class="p-nav">
                @foreach ($groups as [$group, $items])
                    <div class="grp">{{ $group }}</div>
                    @foreach ($items as [$route, $label, $active, $count])
                        <a href="{{ route($route) }}" @if ($active) aria-current="page" @endif>
                            <span>{{ $label }}</span>
                            @if ($count)<span class="count" aria-label="{{ $count }} yeni">{{ $count }}</span>@endif
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
@stack('scripts')
</body>
</html>
