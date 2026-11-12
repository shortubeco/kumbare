@extends('layouts.site', ['seo' => [
    'title' => 'Kumbare – Restoranda öde, ödülün kumbarana düşsün',
    'description' => 'Türkiye genelinde '.\App\Support\Format::number($stats['branches']).' restoranın ödül oranlarını, puanlarını ve menülerini karşılaştır. Her harcamanın bir kısmı ödül olarak geri döner.',
    'canonical' => route('web.home'),
    'robots' => 'index,follow',
]])

@php
    use App\Support\Format;
    use App\Support\Photo;
    use App\Support\Tabela;
    $listTitles = [
        'kebap' => 'En sevilen kebapçılar',
        'pide-lahmacun' => 'En iyi pide ve lahmacun',
        'kahvalti' => 'Kahvaltı için en iyiler',
        'ev-yemekleri' => 'Ev yemeğinde en iyiler',
        'doner' => 'En sevilen dönerciler',
        'tatli-pastane' => 'En iyi tatlıcılar ve pastaneler',
    ];
@endphp

@push('head')
<link rel="preload" as="image" href="/img/hero-sofra.webp" fetchpriority="high">
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@graph' => [
        ['@type' => 'Organization', 'name' => 'Kumbare', 'url' => route('web.home'), 'logo' => url('/favicon.svg')],
        ['@type' => 'WebSite', 'name' => 'Kumbare', 'url' => route('web.home'), 'inLanguage' => 'tr-TR',
            'potentialAction' => ['@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => route('web.branches').'?q={search_term_string}'],
                'query-input' => 'required name=search_term_string']],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
@endpush

@section('content')
<section class="hero hero-foto">
    <div class="wrap">
        <div class="hero-metin">
            <h1>Hesabı öde, ödülün kumbarana düşsün.</h1>
            <p class="hero-lead">Kumbare'deki restoranlar ödediğin tutarın bir kısmını sana ödül olarak geri verir. Ödül, o restoranın kumbarasında birikir; bir sonraki gelişinde hesabından düşersin.</p>

            <form class="hero-search" action="{{ route('web.branches') }}" method="get" role="search">
                <label class="visually-hidden" for="hero-q">Restoran veya mutfak</label>
                <input id="hero-q" name="q" type="search" placeholder="Restoran ya da yemek ara" autocomplete="off">
                <label class="visually-hidden" for="hero-il">Şehir</label>
                <select id="hero-il" name="il">
                    <option value="">Tüm Türkiye</option>
                    @foreach ($cities as $c)
                        <option value="{{ $c->slug }}">{{ $c->name }}</option>
                    @endforeach
                </select>
                <button class="btn btn-coin" type="submit">Restoran bul</button>
            </form>
            <dl class="hero-sayilar">
                <div><dt>Restoran</dt><dd class="num">{{ Format::number($stats['branches']) }}</dd></div>
                <div><dt>İl</dt><dd class="num">{{ $stats['cities'] }}</dd></div>
                <div><dt>Değerlendirme</dt><dd class="num">{{ Format::number($stats['reviews']) }}</dd></div>
            </dl>
            <p class="hero-meta"><a href="#" data-yakinimdaki>Yakınımdaki restoranları göster</a></p>
        </div>

        <div class="hero-sahne" data-adisyon>
            <figure class="hero-gorsel">
                <img src="/img/hero-sofra.webp" width="900" height="760" alt="Sucuklu yumurta, peynir, zeytin ve simitle kurulmuş bir kahvaltı sofrası" fetchpriority="high">
            </figure>
            <div class="adisyon" role="group" aria-labelledby="adisyon-baslik">
                <div class="adisyon-head">
                    <strong id="adisyon-baslik">Adisyon</strong>
                    <span>Ödülünü hesapla</span>
                </div>
                <div class="adisyon-rows">
                    <div class="adisyon-row">
                        <label for="adisyon-tutar">Hesap</label>
                        <span class="leader" aria-hidden="true"></span>
                        <span><input id="adisyon-tutar" class="adisyon-input" inputmode="decimal" value="1.000,00" aria-describedby="adisyon-tl"> <span id="adisyon-tl">TL</span></span>
                    </div>
                    <div class="adisyon-row">
                        <span id="oran-etiket">Ödül oranı</span>
                        <span class="leader" aria-hidden="true"></span>
                        <div class="oran-secim" role="group" aria-labelledby="oran-etiket">
                            @foreach ([5, 10, 15, 20] as $r)
                                <button type="button" data-oran="{{ $r }}" aria-pressed="{{ $r === 10 ? 'true' : 'false' }}">%{{ $r }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="adisyon-total">
                    <span>Kumbarana</span>
                    <output id="adisyon-sonuc" aria-live="polite">100,00 TL</output>
                </div>
                <p class="adisyon-note">Ödül yalnızca kazandığın restoranda geçerli. Kullanırken telefonundan onay verirsin.</p>
            </div>
            <div class="kumbara" aria-hidden="true">
                <span class="coin"></span>
                <svg viewBox="0 0 170 120">
                    <path d="M18 62c-8-2-12-9-8-15" fill="none" stroke="#f6f7f4" stroke-width="5" stroke-linecap="round"/>
                    <rect x="38" y="92" width="18" height="22" rx="6" fill="#dce3de"/>
                    <rect x="108" y="92" width="18" height="22" rx="6" fill="#dce3de"/>
                    <ellipse cx="82" cy="66" rx="64" ry="44" fill="#f6f7f4"/>
                    <path d="M96 28l10-20 12 24z" fill="#dce3de"/>
                    <rect x="138" y="52" width="22" height="28" rx="11" fill="#dce3de"/>
                    <circle cx="146" cy="62" r="2.6" fill="#0e3b3a"/>
                    <circle cx="153" cy="62" r="2.6" fill="#0e3b3a"/>
                    <circle cx="122" cy="52" r="4" fill="#0e3b3a"/>
                    <rect x="64" y="26" width="34" height="6" rx="3" fill="#0e3b3a"/>
                </svg>
            </div>
        </div>
    </div>
</section>

{{-- Mutfağa göre keşfet: fotoğraflı vitrin --}}
<section class="section">
    <div class="wrap">
        <div class="section-head">
            <div>
                <h2>Ne yemek istersin?</h2>
                <p>Mutfağı seç, o mutfaktaki restoranları ödül oranına ve puanına göre karşılaştır.</p>
            </div>
        </div>
        <ul class="mutfak-vitrin">
            @foreach ($categories as $cat)
                <li>
                    <a href="{{ route('web.category', $cat->slug) }}">
                        @if ($src = Photo::category($cat->slug, true))
                            <img src="{{ $src }}" width="480" height="360" alt="" loading="lazy" decoding="async">
                        @else
                            <span class="tabela tabela-{{ Tabela::tone($cat->id) }}" aria-hidden="true"></span>
                        @endif
                        <span class="mutfak-ad">{{ $cat->name }} <small class="num">{{ Format::number($cat->branch_count) }} restoran</small></span>
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</section>

{{-- Listeler panosu --}}
<section class="section section-alt">
    <div class="wrap">
        <div class="section-head">
            <div>
                <h2>Listeler</h2>
                <p>Müşteri puanlarına, değerlendirme sayısına ve ödül oranına göre Türkiye genelindeki sıralamalar.</p>
            </div>
        </div>
        <div class="pano">
            <section class="pano-kolon" aria-labelledby="l-puan">
                <header>
                    <h3 id="l-puan">En yüksek puanlılar</h3>
                    <p>En az {{ \App\Domain\Catalog\BranchSearch::MIN_REVIEWS_FOR_SCORE_RANKING }} değerlendirmesi olanlar</p>
                </header>
                @include('site.partials.rank-list', ['items' => $topScore, 'metric' => 'score'])
                <a class="pano-link" href="{{ route('web.branches', ['siralama' => 'puan']) }}">Tüm puan sıralaması</a>
            </section>
            <section class="pano-kolon" aria-labelledby="l-yorum">
                <header>
                    <h3 id="l-yorum">En çok değerlendirilenler</h3>
                    <p>En çok müşterinin puan verdiği restoranlar</p>
                </header>
                @include('site.partials.rank-list', ['items' => $mostReviewed, 'metric' => 'reviews'])
                <a class="pano-link" href="{{ route('web.branches', ['siralama' => 'yorum']) }}">Tüm liste</a>
            </section>
            <section class="pano-kolon" aria-labelledby="l-odul">
                <header>
                    <h3 id="l-odul">En yüksek ödül</h3>
                    <p>Hesabının en büyük kısmını geri verenler</p>
                </header>
                @include('site.partials.rank-list', ['items' => $topRate, 'metric' => 'rate'])
                <a class="pano-link" href="{{ route('web.branches', ['siralama' => 'odul']) }}">Tüm ödül sıralaması</a>
            </section>
        </div>
    </div>
</section>

@if ($sponsored->isNotEmpty())
<section class="section">
    <div class="wrap">
        <div class="section-head">
            <div>
                <h2>Öne çıkan restoranlar</h2>
                <p>Bu restoranlar bu ay Kumbare'de sponsorlu olarak yer alıyor.</p>
            </div>
        </div>
        <div class="vitrin">
            @foreach ($sponsored as $b)
                <a href="{{ route('web.branch', [$b->business->slug, $b->slug]) }}">
                    <div class="tabela tabela-foto tabela-{{ Tabela::tone($b->business->category_id) }}"
                         @if ($src = Photo::category($b->business->category?->slug, true)) style="--foto: url('{{ $src }}')" @endif>{{ $b->business->name }}</div>
                    <div class="vitrin-meta">
                        <div>
                            <h3>{{ $b->name }}, {{ $b->district?->city?->name }}</h3>
                            <p><span class="tag tag-sponsor">Sponsorlu</span> {{ $b->business->category?->name }}</p>
                        </div>
                        @if ((float) $b->reward_rate > 0)
                            <span class="reward">{{ Format::rate($b->reward_rate) }}</span>
                        @else
                            <span class="reward-none">Ödül yok</span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Mutfak bazlı en iyiler --}}
@if ($categoryLists->isNotEmpty())
<section class="section section-alt">
    <div class="wrap">
        <div class="section-head">
            <div>
                <h2>Mutfağa göre en iyiler</h2>
                <p>Puan ve değerlendirme sayısı birlikte tartılır; tek yorumla 10 alan bir restoran, çok yorumla 9 alanın önüne geçmez.</p>
            </div>
        </div>
        <div class="mutfak-listeleri">
            @foreach ($categoryLists as $row)
                @php $cat = $row['category']; $src = Photo::category($cat->slug); @endphp
                <section class="mutfak-liste" aria-labelledby="ml-{{ $cat->slug }}">
                    <a class="mutfak-liste-baslik" href="{{ route('web.category', $cat->slug) }}">
                        @if ($src)<img src="{{ $src }}" width="1200" height="600" alt="" loading="lazy" decoding="async">@endif
                        <h3 id="ml-{{ $cat->slug }}">{{ $listTitles[$cat->slug] ?? $cat->name }}</h3>
                    </a>
                    @include('site.partials.rank-list', ['items' => $row['branches'], 'metric' => 'score'])
                    <a class="pano-link" href="{{ route('web.category', $cat->slug) }}?siralama=puan">Tüm {{ mb_strtolower(strtr($cat->name, ['I' => 'ı', 'İ' => 'i']), 'UTF-8') }} restoranları</a>
                </section>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Kampanyalı ve popüler --}}
<section class="section">
    <div class="wrap iki-kolon">
        <div>
            <div class="section-head">
                <div>
                    <h2>Kampanyası olanlar</h2>
                    <p>Ekstra ödül, doğum günü ve indirim kampanyaları.</p>
                </div>
                <a class="btn btn-ghost" href="{{ route('web.branches', ['kampanya' => 1]) }}">Tümü</a>
            </div>
            <ul class="restoranlar">
                @foreach ($withCampaign as $b)
                    @include('site.partials.branch-row', ['b' => $b])
                @endforeach
            </ul>
        </div>
        <aside class="pano-kolon">
            <header>
                <h3>Bu ay en popülerler</h3>
                <p>Son 30 günde en çok işlem yapılan restoranlar</p>
            </header>
            @include('site.partials.rank-list', ['items' => $popular, 'metric' => 'popular'])
            <a class="pano-link" href="{{ route('web.branches') }}">Popüler restoranlar</a>
        </aside>
    </div>
</section>

<section class="section section-alt" id="nasil-calisir">
    <div class="wrap nasil">
        <figure class="foto-kart">
            <img src="/img/odeme.webp" width="900" height="700" alt="Restoranda masada telefonla ödeme yapan bir müşteri" loading="lazy" decoding="async">
        </figure>
        <div>
            <h2>Nasıl çalışır?</h2>
            <ol class="adimlar adimlar-dikey">
                <li>
                    <h3>Müşteri numaranı söyle</h3>
                    <p>Uygulamaya üye olunca sana bir müşteri numarası verilir. Hesabı öderken bu numarayı kasaya söylemen yeterli.</p>
                </li>
                <li>
                    <h3>Ödülün kumbarana düşer</h3>
                    <p>Ödediğin tutarın, restoranın ödül oranı kadarı o restoranın kumbarasına eklenir. Her restoranın kumbarası ayrıdır.</p>
                </li>
                <li>
                    <h3>Bir sonraki gelişinde kullan</h3>
                    <p>Biriken ödülü aynı restoranda hesabından düşebilirsin. Kullanmadan önce telefonuna gelen isteği onaylarsın.</p>
                </li>
            </ol>
        </div>
    </div>
</section>

<section class="section">
    <div class="wrap">
        <div class="section-head">
            <div>
                <h2>Şehrini seç</h2>
                <p>Türkiye'nin en kalabalık şehirleri. Diğer iller için listeden seç.</p>
            </div>
            <form class="inline-form" action="{{ route('web.branches') }}" method="get">
                <label class="visually-hidden" for="tum-iller">İl</label>
                <select id="tum-iller" name="il">
                    @foreach ($cities as $c)
                        <option value="{{ $c->slug }}">{{ $c->name }}</option>
                    @endforeach
                </select>
                <button class="btn btn-ghost" type="submit">Göster</button>
            </form>
        </div>
        <ul class="yerler">
            @foreach ($featuredCities as $c)
                <li><a href="{{ route('web.city', $c->slug) }}">{{ $c->name }} <span>{{ $c->branch_count }}</span></a></li>
            @endforeach
        </ul>
    </div>
</section>

<section class="section section-flush-top">
    <div class="wrap">
        <div class="bant">
            <div class="bant-isletme bant-foto">
                <img src="/img/isletme-mutfak.webp" width="900" height="700" alt="Restoran mutfağında sebze doğrayan bir aşçı" loading="lazy" decoding="async">
                <div>
                    <h2>Restoranınız var mı?</h2>
                    <p>Müşterilerinize harcamalarının bir kısmını ödül olarak geri verin; menünüz, kampanyalarınız ve yorumlarınız binlerce kişiye görünsün.</p>
                    <div class="actions"><a class="btn" href="{{ route('web.business') }}">Restoranınızı ekleyin</a></div>
                </div>
            </div>
            <div class="bant-uygulama bant-foto bant-foto-alt" id="uygulama">
                <div>
                    <h2>Kumbaran cebinde</h2>
                    <p>Tüm restoranlardaki ödüllerini, kampanyaları ve onay isteklerini Kumbare uygulamasından takip et.</p>
                    <div class="actions">
                        <span class="store-badge"><small>Yakında</small><b>App Store</b></span>
                        <span class="store-badge"><small>Yakında</small><b>Google Play</b></span>
                    </div>
                </div>
                <img src="/img/sofra-arkadaslar.webp" width="900" height="700" alt="Mumlarla kurulmuş bir akşam yemeği sofrasında kadeh kaldıran arkadaşlar" loading="lazy" decoding="async">
            </div>
        </div>
    </div>
</section>

@if ($newest->isNotEmpty())
<section class="section-tight section-flush-top">
    <div class="wrap">
        <div class="section-head">
            <h2>Kumbare'ye yeni katılanlar</h2>
            <a class="btn btn-ghost" href="{{ route('web.branches', ['siralama' => 'yeni']) }}">Hepsini gör</a>
        </div>
        <ul class="restoranlar">
            @foreach ($newest as $b)
                @include('site.partials.branch-row', ['b' => $b])
            @endforeach
        </ul>
    </div>
</section>
@endif
@endsection
