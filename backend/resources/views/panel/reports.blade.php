@extends('layouts.panel')
@php
    use App\Support\Format; use App\Support\Labels;
    $s = $summary;
    $shortMonths = ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'];
    $teal = '#00897b'; $amber = '#b98600';
    $gLabel = ['day' => 'günlük', 'week' => 'haftalık', 'month' => 'aylık'][$g];
    $axis = fn ($d) => match ($g) {
        'day' => $d->format('j'),
        'week' => $d->format('j').' '.$shortMonths[$d->month - 1],
        default => $shortMonths[$d->month - 1].($d->month === 1 ? ' '.$d->format('y') : ''),
    };
    $tip = fn ($d) => app(\App\Http\Controllers\Panel\ReportController::class)->periodLabel($d, $g);
    $q = $preset === 'ozel' ? ['baslangic' => $from->toDateString(), 'bitis' => $to->toDateString()] : ['aralik' => $preset];
    $csv = fn ($what) => route('panel.reports', $q + ['indir' => $what]);
    // Eksen etiketleri üst üste binmesin: haftalık etiketler uzun olduğu için en fazla ~7, diğerleri ~10 etiket
    $every = max(1, (int) ceil(count($periods) / ($g === 'week' ? 7 : 10)));
@endphp
@section('title', 'Raporlar')

@section('content')
    <div class="p-head">
        <div>
            <h1>Raporlar</h1>
            <p>{{ Labels::date($from) }} – {{ Labels::date($to) }} · {{ Format::number($from->diffInDays($to) + 1) }} gün · dökümler {{ $gLabel }}</p>
        </div>
    </div>

    <nav class="tabs" aria-label="Tarih aralığı">
        @foreach ($presets as $k => $label)
            <a href="{{ route('panel.reports', ['aralik' => $k]) }}" @if ($preset === $k) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <form class="filters" method="get" action="{{ route('panel.reports') }}">
        <div class="field"><label for="baslangic">Başlangıç</label><input id="baslangic" type="date" name="baslangic" value="{{ $from->toDateString() }}" max="{{ now()->toDateString() }}"></div>
        <div class="field"><label for="bitis">Bitiş</label><input id="bitis" type="date" name="bitis" value="{{ $to->toDateString() }}" max="{{ now()->toDateString() }}"></div>
        <button class="btn primary" type="submit">Göster</button>
        <span class="hint muted small">En fazla 366 gün.</span>
    </form>
    @if ($rangeError)<div class="flash err">{{ $rangeError }} Son 30 gün gösteriliyor.</div>@endif

    <div class="stats three">
        <div class="stat"><div class="k">Farklı müşteri</div><div class="v">{{ Format::number((int) $s->customers) }}</div>
            <div class="s">{{ Format::number($s->new_customers) }} yeni · {{ Format::number($s->returning_customers) }} tekrar gelen</div></div>
        <div class="stat"><div class="k">İşlem</div><div class="v">{{ Format::number((int) $s->tx) }}</div>
            <div class="s">{{ Format::number((int) $s->earn_count) }} kazanım · {{ Format::number((int) $s->redeem_count) }} kullanım @if ($s->correction_count) · {{ Format::number((int) $s->correction_count) }} düzeltme @endif</div></div>
        <div class="stat"><div class="k">Ödüllü harcama</div><div class="v money" style="font-size:1.45rem">{{ Format::money($s->spend) }}</div>
            <div class="s">ortalama hesap {{ Format::money($s->avg_spend) }}</div></div>
        <div class="stat gold"><div class="k">Kazandırılan ödül</div><div class="v">{{ Format::money($s->earned, false) }}</div><div class="s">TL · bu dönemde</div></div>
        <div class="stat gold"><div class="k">Kullanılan ödül</div><div class="v">{{ Format::money($s->redeemed, false) }}</div><div class="s">TL · bu dönemde @if ((float) $s->corrected != 0) · düzeltmeler {{ (float) $s->corrected > 0 ? '+' : '' }}{{ Format::money($s->corrected) }}@endif</div></div>
        <div class="stat gold"><div class="k">Kullanılabilir bakiye</div><div class="v">{{ Format::money($s->balance, false) }}</div><div class="s">TL · bugün, {{ Format::number($s->total_customers) }} müşterinin kumbarasında</div></div>
    </div>

    <div class="charts">
        @include('admin.partials.column-chart', [
            'title' => 'Müşteriler',
            'subtitle' => ucfirst($gLabel).' · ödül kazanan veya kullanan farklı müşteri',
            'labels' => array_map($axis, array_column($periods, 'start')),
            'tipLabels' => array_map($tip, array_column($periods, 'start')),
            'every' => $every, 'stacked' => true,
            'series' => [
                ['name' => 'Yeni', 'color' => $amber, 'values' => array_column($periods, 'new')],
                ['name' => 'Tekrar gelen', 'color' => $teal, 'values' => array_column($periods, 'returning')],
            ],
        ])
        @include('admin.partials.column-chart', [
            'title' => 'Ödüller',
            'subtitle' => ucfirst($gLabel).' · kazandırılan ve kullanılan',
            'labels' => array_map($axis, array_column($periods, 'start')),
            'tipLabels' => array_map($tip, array_column($periods, 'start')),
            'every' => $every, 'money' => true,
            'series' => [
                ['name' => 'Kazandırılan', 'color' => $teal, 'values' => array_column($periods, 'earned')],
                ['name' => 'Kullanılan', 'color' => $amber, 'values' => array_column($periods, 'redeemed')],
            ],
        ])
    </div>

    <div class="card flush">
        <div class="card-head"><h2>{{ ucfirst($gLabel) }} döküm</h2><a class="btn sm" href="{{ $csv('donemler') }}">CSV indir</a></div>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead><tr><th>Dönem</th><th class="n">İşlem</th><th class="n">Müşteri</th><th class="n">Yeni</th><th class="n">Tekrar</th><th class="n">Harcama</th><th class="n">Kazandırılan</th><th class="n">Kullanılan</th></tr></thead>
                <tbody>
                @foreach (array_reverse($periods) as $p)
                    <tr @if ($p->tx === 0) class="muted" @endif>
                        <td class="nowrap">{{ $tip($p->start) }}</td>
                        <td class="n">{{ Format::number($p->tx) }}</td>
                        <td class="n">{{ Format::number($p->customers) }}</td>
                        <td class="n">{{ Format::number($p->new) }}</td>
                        <td class="n">{{ Format::number($p->returning) }}</td>
                        <td class="n money">{{ Format::money($p->spend) }}</td>
                        <td class="n money">{{ Format::money($p->earned) }}</td>
                        <td class="n money">{{ Format::money($p->redeemed) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid two" style="align-items:start">
        <div class="card flush">
            <div class="card-head"><h2>Kampanya performansı</h2><a class="btn sm" href="{{ $csv('kampanyalar') }}">CSV indir</a></div>
            @if (empty($campaigns))
                <p class="empty">Bu dönemde ödül kazandırılmadı.</p>
            @else
                <div class="tbl-wrap">
                    <table class="tbl">
                        <thead><tr><th>Kampanya</th><th class="n">İşlem</th><th class="n">Müşteri</th><th class="n">Ödül</th></tr></thead>
                        <tbody>
                        @foreach ($campaigns as $c)
                            <tr>
                                <td>{{ $c->title }}<span class="sub">{{ $c->reward_rate !== null ? Format::rate($c->reward_rate) : 'Şube oranı' }} · harcama {{ Format::money($c->spend) }}</span></td>
                                <td class="n">{{ Format::number((int) $c->tx) }}</td>
                                <td class="n">{{ Format::number((int) $c->customers) }}</td>
                                <td class="n money">{{ Format::money($c->earned) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="card flush">
            <div class="card-head"><h2>En aktif müşteriler</h2><a class="btn sm" href="{{ $csv('musteriler') }}">CSV indir</a></div>
            @if (empty($customers))
                <p class="empty">Bu dönemde işlem yok.</p>
            @else
                <div class="tbl-wrap">
                    <table class="tbl">
                        <thead><tr><th class="n">No</th><th>Müşteri</th><th class="n">Ziyaret</th><th class="n">Harcama</th><th class="n">Bakiye</th></tr></thead>
                        <tbody>
                        @foreach ($customers as $c)
                            <tr>
                                <td class="n num">{{ $c->customer_no }}</td>
                                <td>{{ $c->first_name }} {{ $c->last_name }}</td>
                                <td class="n">{{ Format::number((int) $c->visits) }}</td>
                                <td class="n money">{{ Format::money($c->spend) }}</td>
                                <td class="n money">{{ Format::money($c->balance) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="muted small" style="padding:0 20px 14px">İlk 20 gösteriliyor; CSV'de ilk 500 müşteri var.</p>
            @endif
        </div>
    </div>

    @if ($staff !== null)
        <div class="card flush">
            <h2>Personel</h2>
            @if (empty($staff))
                <p class="empty">Bu dönemde işlem yok.</p>
            @else
                <div class="tbl-wrap">
                    <table class="tbl">
                        <thead><tr><th>Kişi</th><th class="n">Kazandırma</th><th class="n">Kullandırma</th><th class="n">Kazandırılan ödül</th></tr></thead>
                        <tbody>
                        @foreach ($staff as $p)
                            <tr>
                                <td>{{ $p->first_name }} {{ $p->last_name }}<span class="sub">{{ Labels::text('role', $p->role) }}</span></td>
                                <td class="n">{{ Format::number((int) $p->earn_count) }}</td>
                                <td class="n">{{ Format::number((int) $p->redeem_count) }}</td>
                                <td class="n money">{{ Format::money($p->earned) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
@endsection
