<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $seo['title'] ?? 'Kumbare – Restoranlarda ödül kazan' }}</title>
    @if (! empty($seo['description']))
        <meta name="description" content="{{ $seo['description'] }}">
    @endif
    <meta name="robots" content="{{ $seo['robots'] ?? 'index,follow' }}">
    @if (! empty($seo['canonical']))
        <link rel="canonical" href="{{ $seo['canonical'] }}">
    @endif
    <meta name="theme-color" content="#0e3b3a">
    {{-- JavaScript varsa telefon menüsü çekmece olur; yoksa bağlantılar başlıkta iki satır halinde görünür. --}}
    <script>document.documentElement.classList.add('js');</script>

    <meta property="og:site_name" content="Kumbare">
    <meta property="og:locale" content="tr_TR">
    <meta property="og:type" content="{{ $ogType ?? 'website' }}">
    <meta property="og:title" content="{{ $seo['title'] ?? 'Kumbare' }}">
    @if (! empty($seo['description']))
        <meta property="og:description" content="{{ $seo['description'] }}">
    @endif
    <meta property="og:url" content="{{ $seo['canonical'] ?? url()->current() }}">
    <meta name="twitter:card" content="summary">

    <link rel="preload" href="/fonts/bricolage-grotesque-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/source-sans-3-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/css/site.css?v={{ @filemtime(public_path('css/site.css')) }}">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">

    @stack('head')
</head>
<body>
<a class="skip" href="#icerik">İçeriğe geç</a>

<header class="site-header">
    <div class="wrap">
        <a class="brand" href="{{ route('web.home') }}" aria-label="Kumbare ana sayfa">
            @include('site.partials.logo')
            <span>kumbare</span>
        </a>
        {{-- Telefonda (JavaScript açıkken) bu menü soldan kayan çekmeceye dönüşür; site.js "Menü" düğmesiyle açar. --}}
        <nav class="site-nav" id="site-menu" aria-label="Ana menü">
            <div class="menu-ust">
                <span>Menü</span>
                <button class="menu-kapat" type="button" data-menu-kapat aria-label="Menüyü kapat">
                    <svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
                </button>
            </div>
            <a href="{{ route('web.branches') }}" @if (request()->routeIs('web.branches', 'web.city', 'web.district', 'web.category', 'web.branch')) aria-current="page" @endif>Restoranlar</a>
            <a href="{{ route('web.home') }}#nasil-calisir">Nasıl çalışır</a>
            <a href="{{ route('web.business') }}" @if (request()->routeIs('web.business*')) aria-current="page" @endif>İşletmeler için</a>
            <a class="menu-musteri" href="{{ route('web.app') }}" @if (request()->routeIs('web.app')) aria-current="page" @endif>Müşteriler için</a>
        </nav>
        {{-- Masaüstü düğmesi; telefonda gizlenir, aynı bağlantı menü çekmecesinde (JavaScript yoksa burada görünür). --}}
        <a class="nav-cta" href="{{ route('web.app') }}" @if (request()->routeIs('web.app')) aria-current="page" @endif aria-label="Müşteriler için: ücretsiz Kumbare uygulaması">Müşteriler için</a>
        <button class="menu-ac" type="button" data-menu-ac aria-controls="site-menu" aria-expanded="false" aria-label="Menüyü aç">
            <svg viewBox="0 0 24 24" width="26" height="26" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
        </button>
    </div>
    <div class="menu-perde" data-menu-perde></div>
</header>

<main id="icerik">
    @yield('content')
</main>

<footer class="site-footer">
    <div class="wrap">
        <div class="cols">
            <div>
                <a class="brand" href="{{ route('web.home') }}">@include('site.partials.logo')<span>kumbare</span></a>
                <p>Restoranda öde, ödülün o restoranın kumbarasında birikir. Bir sonraki gelişinde hesabından düşersin.</p>
            </div>
            <div>
                <h2>Keşfet</h2>
                <ul>
                    <li><a href="{{ route('web.branches') }}">Tüm restoranlar</a></li>
                    <li><a href="{{ route('web.branches', ['siralama' => 'odul']) }}">En yüksek ödül</a></li>
                    <li><a href="{{ route('web.branches', ['siralama' => 'puan']) }}">En yüksek puan</a></li>
                    <li><a href="{{ route('web.branches', ['kampanya' => 1]) }}">Kampanyalı restoranlar</a></li>
                </ul>
            </div>
            <div>
                <h2>Büyük şehirler</h2>
                <ul>
                    @foreach (['istanbul' => 'İstanbul', 'ankara' => 'Ankara', 'izmir' => 'İzmir', 'bursa' => 'Bursa', 'antalya' => 'Antalya'] as $slug => $name)
                        <li><a href="{{ route('web.city', $slug) }}">{{ $name }} restoranları</a></li>
                    @endforeach
                </ul>
            </div>
            <div>
                <h2>Kumbare</h2>
                <ul>
                    <li><a href="{{ route('web.business') }}">Restoranınızı ekleyin</a></li>
                    <li><a href="{{ route('web.app') }}">Mobil uygulama</a></li>
                    <li><a href="{{ route('web.kvkk') }}">KVKK aydınlatma metni</a></li>
                </ul>
            </div>
        </div>
        <div class="legal">
            <span>© {{ now()->year }} Kumbare</span>
            <span>Ödüller nakit değildir; yalnızca kazanıldığı restoranda kullanılır.</span>
            <span class="foto-kredi">Mutfak fotoğrafları temsilîdir, kaynak: <a href="https://unsplash.com" rel="noopener" target="_blank">Unsplash</a></span>
        </div>
    </div>
</footer>

<script src="/js/site.js?v={{ @filemtime(public_path('js/site.js')) }}" defer></script>
@stack('scripts')
</body>
</html>
