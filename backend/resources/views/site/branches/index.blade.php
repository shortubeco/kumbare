@extends('layouts.site')

@php
    $crumbs = [['Ana sayfa', route('web.home')], ['Restoranlar', route('web.branches')]];
    if ($category && ! $city) { $crumbs[] = [$category->name, route('web.category', $category->slug)]; }
    if ($city) { $crumbs[] = [$city->name, route('web.city', $city->slug)]; }
    if ($district) { $crumbs[] = [$district->displayName(), route('web.district', [$city->slug, $district->slug])]; }
    $formAction = $category && ! $city ? route('web.category', $category->slug) : route('web.branches');
    $hasFilters = collect($filters)->except(['sayfa'])->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
@endphp

@push('head')
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@graph' => [
        ['@type' => 'BreadcrumbList', 'itemListElement' => collect($crumbs)->values()->map(fn ($c, $i) => [
            '@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0], 'item' => $c[1],
        ])->all()],
        ['@type' => 'ItemList', 'name' => $h1, 'numberOfItems' => $branches->total(),
            'itemListElement' => $branches->getCollection()->values()->map(fn ($b, $i) => [
                '@type' => 'ListItem', 'position' => ($branches->firstItem() ?? 1) + $i,
                'url' => route('web.branch', [$b->business->slug, $b->slug]),
                'name' => $b->business->name.' '.$b->name,
            ])->all()],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
@endpush

@section('content')
<div class="wrap">
    <header class="page-head">
        <nav class="breadcrumb" aria-label="Konum">
            <ol>
                @foreach ($crumbs as $i => $c)
                    <li>@if ($i === count($crumbs) - 1)<span aria-current="page">{{ $c[0] }}</span>@else<a href="{{ $c[1] }}">{{ $c[0] }}</a>@endif</li>
                @endforeach
            </ol>
        </nav>
        @if ($category && ($catPhoto = \App\Support\Photo::category($category->slug)))
            <div class="kategori-baslik">
                <img src="{{ $catPhoto }}" width="1200" height="600" alt="" fetchpriority="high">
                <h1>{{ $h1 }}</h1>
            </div>
        @else
            <h1>{{ $h1 }}</h1>
        @endif
        <p>{{ $intro }}</p>
    </header>

    <div class="liste-layout">
        <form class="filtre" action="{{ $formAction }}" method="get" data-filtre data-collapsed="true" aria-label="Filtreler">
            <div class="filtre-title">
                <h2>Filtrele</h2>
                <button class="btn btn-ghost filtre-toggle" type="button" data-filtre-ac aria-expanded="false">Filtreleri göster</button>
            </div>

            <div class="field">
                <label for="f-q">Ara</label>
                <input id="f-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Restoran veya mutfak">
            </div>
            <div class="field">
                <label for="f-il">İl</label>
                <select id="f-il" name="il" data-il-secim>
                    <option value="">Tüm Türkiye</option>
                    @foreach ($cities as $c)
                        <option value="{{ $c->slug }}" @selected($city?->id === $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            @if ($city)
                <div class="field">
                    <label for="f-ilce">İlçe</label>
                    <select id="f-ilce" name="ilce">
                        <option value="">Tüm ilçeler</option>
                        @foreach ($districts as $d)
                            <option value="{{ $d->slug }}" @selected($district?->id === $d->id)>{{ $d->name === 'Merkez' ? $city->name.' Merkez' : $d->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            @unless ($category && ! $city && request()->routeIs('web.category'))
                <div class="field">
                    <label for="f-mutfak">Mutfak</label>
                    <select id="f-mutfak" name="mutfak">
                        <option value="">Tüm mutfaklar</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->slug }}" @selected($category?->id === $cat->id)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endunless
            <div class="field">
                <label for="f-odul">En az ödül oranı</label>
                <select id="f-odul" name="min_odul">
                    <option value="">Fark etmez</option>
                    @foreach ([5, 10, 15, 20] as $r)
                        <option value="{{ $r }}" @selected(($filters['min_odul'] ?? '') == $r)>%{{ $r }} ve üzeri</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="f-puan">En az puan</label>
                <select id="f-puan" name="min_puan">
                    <option value="">Fark etmez</option>
                    @foreach ([9, 8, 7] as $p)
                        <option value="{{ $p }}" @selected(($filters['min_puan'] ?? '') == $p)>{{ $p }} ve üzeri</option>
                    @endforeach
                </select>
            </div>
            <label class="check"><input type="checkbox" name="kampanya" value="1" @checked(! empty($filters['kampanya']))> Kampanyası olanlar</label>
            <label class="check"><input type="checkbox" name="yeni" value="1" @checked(! empty($filters['yeni']))> Son 30 günde katılanlar</label>
            <input type="hidden" name="siralama" value="{{ $sortKey }}">
            @if ($sortKey === 'yakin')
                <input type="hidden" name="lat" value="{{ $filters['lat'] ?? '' }}">
                <input type="hidden" name="lng" value="{{ $filters['lng'] ?? '' }}">
            @endif
            <div class="actions">
                <button class="btn" type="submit">Uygula</button>
                @if ($hasFilters)
                    <a class="btn btn-ghost" href="{{ $formAction }}">Temizle</a>
                @endif
            </div>
        </form>

        <section aria-labelledby="sonuc-baslik">
            <div class="sonuc-bar">
                <h2 id="sonuc-baslik" class="visually-hidden">Sonuçlar</h2>
                <span class="count num">{{ \App\Support\Format::number($branches->total()) }} restoran</span>
                <form method="get" action="{{ url()->current() }}" data-siralama>
                    @foreach (collect($filters)->except(['siralama', 'sayfa', 'lat', 'lng']) as $k => $v)
                        @if (is_scalar($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
                    @endforeach
                    <label for="siralama" class="visually-hidden">Sıralama</label>
                    <select id="siralama" name="siralama" data-siralama-secim>
                        @foreach ($sorts as $key => [$_, $label])
                            <option value="{{ $key }}" @selected($sortKey === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <noscript><button class="btn btn-ghost" type="submit">Sırala</button></noscript>
                </form>
            </div>

            @if ($sortKey === 'puan')
                <p class="liste-not">En yüksek puan sıralamasına yalnızca en az 10 değerlendirmesi olan restoranlar girer; diğerleri listenin sonunda yer alır.</p>
            @endif

            @if ($branches->isEmpty())
                <div class="bos">
                    <h3>Bu filtrelere uyan restoran yok</h3>
                    <p>Ödül veya puan sınırını düşürmeyi ya da başka bir ilçeye bakmayı deneyebilirsin.</p>
                    <a class="btn btn-ghost" href="{{ $formAction }}">Filtreleri temizle</a>
                </div>
            @else
                <ul class="restoranlar">
                    @foreach ($branches as $b)
                        @include('site.partials.branch-row', ['b' => $b])
                    @endforeach
                </ul>
                @include('site.partials.pagination', ['paginator' => $branches])
            @endif
        </section>
    </div>
</div>
@endsection
