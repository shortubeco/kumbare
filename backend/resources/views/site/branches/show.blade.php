@extends('layouts.site', ['ogType' => 'restaurant.restaurant'])

@php
    use App\Support\Format;
    use App\Support\Tabela;
    $city = $branch->district->city;
    $rate = (float) $branch->reward_rate;
    $dayCodes = [1 => 'Mo', 2 => 'Tu', 3 => 'We', 4 => 'Th', 5 => 'Fr', 6 => 'Sa', 7 => 'Su'];
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Restaurant',
        'name' => $business->name.' '.$branch->name,
        'url' => route('web.branch', [$business->slug, $branch->slug]),
        'servesCuisine' => $business->category?->name,
        'telephone' => $branch->phone,
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => $branch->address,
            'addressLocality' => $branch->district->displayName(),
            'addressRegion' => $city->name,
            'addressCountry' => 'TR',
        ],
        'openingHours' => $branch->hours->reject(fn ($h) => $h->is_closed)
            ->map(fn ($h) => $dayCodes[$h->weekday].' '.substr($h->opens_at, 0, 5).'-'.substr($h->closes_at, 0, 5))->values()->all(),
    ];
    if ($branch->latitude) {
        $schema['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => (float) $branch->latitude, 'longitude' => (float) $branch->longitude];
    }
    if ($branch->rating_count > 0) {
        $schema['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => (float) $branch->rating_avg,
            'bestRating' => 10, 'worstRating' => 1, 'ratingCount' => $branch->rating_count];
    }
    $breadcrumb = [
        ['Ana sayfa', route('web.home')],
        ['Restoranlar', route('web.branches')],
        [$city->name, route('web.city', $city->slug)],
        [$branch->district->displayName(), route('web.district', [$city->slug, $branch->district->slug])],
        [$business->name.' '.$branch->name, route('web.branch', [$business->slug, $branch->slug])],
    ];
    $mapsUrl = $branch->latitude
        ? 'https://www.google.com/maps/dir/?api=1&destination='.$branch->latitude.','.$branch->longitude
        : 'https://www.google.com/maps/search/?api=1&query='.urlencode($business->name.' '.$branch->address);
    $maxBar = max(1, max($distribution));
@endphp

@push('head')
<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
    'itemListElement' => collect($breadcrumb)->values()->map(fn ($c, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0], 'item' => $c[1]])->all()],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endpush

@section('content')
<div class="wrap">
    <header class="page-head">
        <nav class="breadcrumb" aria-label="Konum">
            <ol>
                @foreach ($breadcrumb as $i => $c)
                    <li>@if ($i === count($breadcrumb) - 1)<span aria-current="page">{{ $business->name }}</span>@else<a href="{{ $c[1] }}">{{ $c[0] }}</a>@endif</li>
                @endforeach
            </ol>
        </nav>
    </header>

    <div class="detay-head">
        @php
            // Şubenin kendi fotoğrafı varsa kapak odur; yoksa mutfağa göre temsilî görsel
            $ownPhoto = $branch->photos->first() ? \App\Http\Resources\Support::fileUrl($branch->photos->first()->path) : null;
            $catPhoto = $ownPhoto ?: \App\Support\Photo::category($business->category?->slug);
        @endphp
        <div class="tabela tabela-lg tabela-{{ Tabela::tone($business->category_id) }} @if ($catPhoto) tabela-foto @endif"
             @if ($catPhoto) style="--foto: url('{{ $catPhoto }}')" @endif aria-hidden="true">
            {{ $business->name }}
            @if ($catPhoto && ! $ownPhoto)<span class="temsili">Temsilî görsel</span>@endif
        </div>
        <div class="detay-ozet">
            <div>
                <h1>{{ $business->name }} <span class="alt">{{ $branch->name }}</span></h1>
                <p class="alt">{{ $business->category?->name }} — {{ $branch->district->displayName() }}, {{ $city->name }}</p>
            </div>
            <div>@include('site.partials.score', ['avg' => $branch->rating_avg, 'count' => $branch->rating_count])</div>
            <div class="odul-kutu">
                @if ($rate > 0)
                    <span class="reward">{{ Format::rate($branch->reward_rate) }}</span>
                    <strong>ödül kazanırsın</strong>
                    <p>1.000 TL'lik hesapta kumbarana {{ Format::money(round(1000 * $rate / 100, 2)) }} düşer.</p>
                @else
                    <span class="reward reward-none">Ödül yok</span>
                    <strong>Bu şube şu anda ödül vermiyor</strong>
                    <p>Daha önce biriken ödüller kullanılabilir.</p>
                @endif
            </div>
            <div class="inline-form">
                @if ($openNow === true)
                    <span class="durum durum-acik">Şu an açık</span>
                @elseif ($openNow === false)
                    <span class="durum durum-kapali">Şu an kapalı</span>
                @endif
                @if ($branch->is_sponsored)<span class="tag tag-sponsor">Sponsorlu</span>@endif
            </div>
        </div>
    </div>

    <div class="detay-layout">
        <div>
            @if ($branch->photos->count() > 1)
                <section class="blok" aria-label="Fotoğraflar">
                    <div class="galeri">
                        @foreach ($branch->photos as $p)
                            <a href="{{ \App\Http\Resources\Support::fileUrl($p->path) }}" target="_blank" rel="noopener">
                                <img src="{{ \App\Http\Resources\Support::fileUrl($p->path) }}" alt="{{ $business->name }} {{ $branch->name }} fotoğrafı {{ $loop->iteration }}" loading="lazy" width="320" height="240">
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
            @if ($campaigns->isNotEmpty())
                <section class="blok" aria-labelledby="kampanyalar">
                    <h2 id="kampanyalar">Kampanyalar</h2>
                    <div class="kampanyalar">
                        @foreach ($campaigns as $c)
                            <article class="kampanya">
                                @if ($c->reward_rate !== null)
                                    <span class="reward">{{ Format::rate($c->reward_rate) }}</span>
                                @else
                                    <span class="reward" aria-hidden="true">%</span>
                                @endif
                                <div>
                                    <h3>{{ $c->title }}</h3>
                                    @if ($c->description)<p>{{ $c->description }}</p>@endif
                                    <p>
                                        @if ($c->birthday_only) Yalnızca doğum gününde geçerli. @endif
                                        @if ($c->new_customers_only) Bu şubede ilk kez ödül kazanacaklara özel. @endif
                                        @if ($c->weekdayList()) Geçerli günler: {{ collect($c->weekdayList())->map(fn ($d) => Format::DAYS[$d])->join(', ') }}. @endif
                                        {{ Format::date($c->ends_at) }} tarihine kadar.
                                    </p>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="blok" aria-labelledby="menu">
                <h2 id="menu">Menü ve fiyatlar</h2>
                @if ($menu->isEmpty())
                    <p class="alt">Bu restoran henüz menüsünü eklemedi.</p>
                @else
                    @if ($menu->count() > 1)
                        <nav class="menu-nav" aria-label="Menü kategorileri">
                            @foreach ($menu as $cat)
                                <a href="#menu-{{ $cat->id }}">{{ $cat->name }}</a>
                            @endforeach
                        </nav>
                    @endif
                    @foreach ($menu as $cat)
                        <div class="menu-kategori" id="menu-{{ $cat->id }}">
                            <h3>{{ $cat->name }}</h3>
                            @foreach ($cat->items as $item)
                                <div class="menu-urun @unless ($item->is_available) tukendi @endunless">
                                    <strong>{{ $item->name }}@unless ($item->is_available) <span class="tag">Şu an yok</span>@endunless</strong>
                                    <span class="fiyat">{{ Format::money($item->price) }}</span>
                                    @if ($item->description)<p>{{ $item->description }}</p>@endif
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                    <p class="liste-not">Fiyatlar restoran tarafından güncellenir.</p>
                @endif
            </section>

            <section class="blok" id="degerlendirmeler" aria-labelledby="yorumlar-baslik">
                <h2 id="yorumlar-baslik">Değerlendirmeler</h2>
                @if ($branch->rating_count > 0)
                    <div class="puan-ozet">
                        <div class="puan-buyuk">{{ Format::score($branch->rating_avg) }} <small>/ 10</small>
                            <p class="liste-not">{{ Format::number($branch->rating_count) }} değerlendirme</p>
                        </div>
                        <div class="dagilim" aria-label="Puan dağılımı">
                            @foreach ($distribution as $score => $count)
                                <div><span>{{ $score }}</span><span class="bar"><i style="width: {{ round($count / $maxBar * 100) }}%"></i></span><span>{{ $count }}</span></div>
                            @endforeach
                        </div>
                    </div>
                    <div class="sonuc-bar">
                        <span class="liste-not">Yalnızca bu şubede ödül kazanmış müşteriler değerlendirme yapabilir.</span>
                        <nav class="inline-form" aria-label="Yorum sıralaması">
                            @foreach (['yeni' => 'En yeni', 'eski' => 'En eski', 'puan' => 'Puana göre'] as $k => $label)
                                <a class="tag" href="{{ route('web.branch', [$business->slug, $branch->slug]) }}?yorumlar={{ $k }}#degerlendirmeler" @if ($reviewSort === $k) aria-current="true" @endif>{{ $label }}</a>
                            @endforeach
                        </nav>
                    </div>
                    <ul class="yorumlar">
                        @foreach ($reviews as $r)
                            <li class="yorum">
                                <header>
                                    <span class="puan-rozet">{{ $r->score }} / 10</span>
                                    <strong>{{ $r->customer?->publicName() }}</strong>
                                    <time datetime="{{ $r->created_at->toDateString() }}">{{ Format::date($r->created_at) }}</time>
                                </header>
                                @if ($r->comment)<p>{{ $r->comment }}</p>@endif
                                @if ($r->reply)
                                    <div class="cevap"><strong>Restoranın cevabı</strong>{{ $r->reply->body }}</div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    @include('site.partials.pagination', ['paginator' => $reviews])
                @else
                    <p class="alt">Henüz değerlendirme yok. Bu şubede ödül kazanan müşteriler uygulamadan puan verebilir.</p>
                @endif
            </section>
        </div>

        <aside>
            <div class="kutu">
                <h2>İletişim ve ulaşım</h2>
                <dl>
                    <div><dt>Adres</dt><dd>{{ $branch->address }}</dd></div>
                    @if ($branch->phone)<div><dt>Telefon</dt><dd><a href="tel:{{ $branch->phone }}">{{ $branch->phone }}</a></dd></div>@endif
                </dl>
                <a class="btn btn-ghost" href="{{ $mapsUrl }}" target="_blank" rel="noopener">Yol tarifi al</a>
            </div>

            @if ($branch->hours->isNotEmpty())
                <div class="kutu">
                    <h2>Çalışma saatleri</h2>
                    <table class="saatler">
                        <tbody>
                        @foreach ($branch->hours as $h)
                            <tr @if ($h->weekday === $today) class="bugun" @endif>
                                <td>{{ Format::DAYS[$h->weekday] }}@if ($h->weekday === $today) (bugün)@endif</td>
                                <td>{{ $h->is_closed ? 'Kapalı' : substr($h->opens_at, 0, 5).' – '.substr($h->closes_at, 0, 5) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="kutu">
                <h2>Ödül koşulları</h2>
                <p>{{ $branch->reward_terms ?: 'Biriken ödüller yalnızca bu şubede kullanılabilir.' }}</p>
            </div>

            @if ($otherBranches->isNotEmpty())
                <div class="kutu">
                    <h2>{{ $business->name }} diğer şubeleri</h2>
                    <ul class="subeler">
                        @foreach ($otherBranches->take(12) as $o)
                            <li><a href="{{ route('web.branch', [$business->slug, $o->slug]) }}"><span>{{ $o->name }}, {{ $o->district?->city?->name }}</span><span class="num">{{ (float) $o->reward_rate > 0 ? Format::rate($o->reward_rate) : 'Ödül yok' }}</span></a></li>
                        @endforeach
                    </ul>
                    @if ($otherBranches->count() > 12)
                        <p class="liste-not">ve {{ $otherBranches->count() - 12 }} şube daha</p>
                    @endif
                </div>
            @endif
        </aside>
    </div>
</div>
@endsection
