@extends('layouts.admin')
@section('title', 'Genel bakış')

@php use App\Support\Format; use App\Support\Labels; @endphp

@section('content')
    <div class="p-head">
        <div>
            <h1>Genel bakış</h1>
            <p>{{ Labels::dateTime(now()) }} itibarıyla platformun durumu.</p>
        </div>
    </div>

    <div class="todo">
        <a href="{{ route('admin.businesses', ['durum' => 'pending']) }}" @class(['hot' => $todo['applications'] > 0])>
            <span>Bekleyen başvuru</span><b>{{ Format::number($todo['applications']) }}</b>
        </a>
        <a href="{{ route('admin.payments') }}" @class(['hot' => $todo['payments'] > 0])>
            <span>Bekleyen havale</span><b>{{ Format::number($todo['payments']) }}</b>
        </a>
        <a href="{{ route('admin.reports') }}" @class(['hot' => $todo['reports'] > 0])>
            <span>Şikâyet edilen yorum</span><b>{{ Format::number($todo['reports']) }}</b>
        </a>
    </div>

    <h2 style="margin-bottom:10px">İşletme ve şubeler</h2>
    <div class="stats five">
        <div class="stat"><div class="k">İşletme</div><div class="v">{{ Format::number($businessTotal) }}</div>
            <div class="s">{{ Format::number($businesses['approved'] ?? 0) }} aktif · {{ Format::number(($businesses['suspended'] ?? 0) + ($businesses['closed'] ?? 0)) }} kapalı</div></div>
        <div class="stat"><div class="k">Şube</div><div class="v">{{ Format::number($branchTotal) }}</div>
            <div class="s">{{ Format::number($branchActive) }} şube yayında</div></div>
        <div class="stat"><div class="k">Profesyonel paket</div><div class="v">{{ Format::number($proBranches) }}</div>
            <div class="s">şubelerin yüzdesi: %{{ $branchActive ? number_format($proBranches * 100 / $branchActive, 1, ',', '') : 0 }}</div></div>
        <div class="stat"><div class="k">Ücretsiz paket</div><div class="v">{{ Format::number($freeBranches) }}</div></div>
        <div class="stat"><div class="k">Bu ay sponsorlu</div><div class="v">{{ Format::number($sponsoredThisMonth) }}</div></div>
    </div>

    <h2 style="margin-bottom:10px">Müşteriler ve işlemler</h2>
    <div class="stats three">
        <div class="stat"><div class="k">Toplam müşteri</div><div class="v">{{ Format::number($customerTotal) }}</div>
            <div class="s">{{ Format::number($customerPassive) }} pasif</div></div>
        <div class="stat"><div class="k">Aktif müşteri</div><div class="v">{{ Format::number($customerActive30) }}</div>
            <div class="s">son 30 günde işlem yapan</div></div>
        <div class="stat"><div class="k">Toplam işlem</div><div class="v">{{ Format::number((int) $tx->cnt) }}</div>
            <div class="s">son 30 günde {{ Format::number((int) $tx->cnt30) }}</div></div>
        <div class="stat gold"><div class="k">Kazandırılan ödül</div><div class="v">{{ Format::money($tx->earned, false) }}</div><div class="s">TL</div></div>
        <div class="stat gold"><div class="k">Kullanılan ödül</div><div class="v">{{ Format::money($tx->redeemed, false) }}</div><div class="s">TL</div></div>
        <div class="stat gold"><div class="k">Kumbaralardaki bakiye</div><div class="v">{{ Format::money($balance, false) }}</div><div class="s">TL</div></div>
    </div>

    @php
        $shortMonths = ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'];
        $mLabels = collect($monthly)->map(fn ($r) => $shortMonths[\Carbon\Carbon::parse($r->month)->month - 1])->all();
        $mTips = collect($monthly)->map(fn ($r) => Labels::month($r->month))->all();
        $dLabels = collect($daily)->map(fn ($r) => \Carbon\Carbon::parse($r->day)->format('j'))->all();
        $dTips = collect($daily)->map(fn ($r) => Labels::date($r->day))->all();
        $teal = '#00897b';
        $amber = '#b98600';
    @endphp

    <h2 class="charts-title">Müşteri ve işlem grafikleri</h2>
    <div class="charts">
        @include('admin.partials.column-chart', [
            'title' => 'Günlük işlem sayısı',
            'subtitle' => 'Son 30 gün · kazanım, kullanım ve düzeltmeler',
            'labels' => $dLabels, 'tipLabels' => $dTips, 'every' => 5,
            'series' => [['name' => 'İşlem', 'color' => $teal, 'values' => collect($daily)->pluck('cnt')->all()]],
        ])
        @include('admin.partials.column-chart', [
            'title' => 'Ödüller',
            'subtitle' => 'Son 6 ay · aylık toplam',
            'labels' => $mLabels, 'tipLabels' => $mTips, 'money' => true,
            'series' => [
                ['name' => 'Kazandırılan', 'color' => $teal, 'values' => collect($monthly)->pluck('earned')->all()],
                ['name' => 'Kullanılan', 'color' => $amber, 'values' => collect($monthly)->pluck('redeemed')->all()],
            ],
        ])
        @include('admin.partials.column-chart', [
            'title' => 'Yeni müşteri kaydı',
            'subtitle' => 'Son 6 ay · uygulamadan kayıt olan',
            'labels' => $mLabels, 'tipLabels' => $mTips,
            'series' => [['name' => 'Yeni müşteri', 'color' => $teal, 'values' => collect($monthly)->pluck('new_customers')->all()]],
        ])
        @include('admin.partials.column-chart', [
            'title' => 'Gelirler',
            'subtitle' => 'Son 6 ay · onaylanan havaleler, onay ayında',
            'labels' => $mLabels, 'tipLabels' => $mTips, 'money' => true, 'stacked' => true,
            'series' => [
                ['name' => 'Abonelik', 'color' => $teal, 'values' => collect($monthly)->pluck('rev_sub')->all()],
                ['name' => 'Sponsorluk', 'color' => $amber, 'values' => collect($monthly)->pluck('rev_spo')->all()],
            ],
        ])
    </div>

    <h2 class="charts-title">İşletme grafikleri</h2>
    <div class="charts">
        @include('admin.partials.column-chart', [
            'title' => 'Yeni işletme ve şube',
            'subtitle' => 'Son 6 ay · onaylanan işletmeler, açılan şubeler',
            'labels' => $mLabels, 'tipLabels' => $mTips,
            'series' => [
                ['name' => 'Yeni işletme', 'color' => $teal, 'values' => collect($bizMonthly)->pluck('new_businesses')->all()],
                ['name' => 'Yeni şube', 'color' => $amber, 'values' => collect($bizMonthly)->pluck('new_branches')->all()],
            ],
        ])
        @include('admin.partials.column-chart', [
            'title' => 'Profesyonel paketli şube',
            'subtitle' => 'Son 6 ay · her ayın sonundaki sayı (bu ay: bugün)',
            'labels' => $mLabels, 'tipLabels' => $mTips,
            'series' => [['name' => 'Profesyonel şube', 'color' => $teal, 'values' => collect($bizMonthly)->pluck('pro_branches')->all()]],
        ])
        @include('admin.partials.bar-list', [
            'title' => 'En çok işlem yapan şubeler',
            'subtitle' => 'Son 30 gün · ilk 10',
            'color' => $teal, 'unit' => 'işlem',
            'rows' => collect($topBranches)->map(fn ($b) => [
                'label' => $b->business_name.' – '.$b->name,
                'sub' => $b->city,
                'value' => (int) $b->cnt,
                'url' => route('admin.businesses.show', $b->business_id),
            ])->all(),
        ])
        @include('admin.partials.bar-list', [
            'title' => 'Mutfağa göre şubeler',
            'subtitle' => 'Yayındaki şubeler',
            'color' => $amber, 'unit' => 'şube',
            'rows' => collect($byCategory)->map(fn ($c) => ['label' => $c->name, 'value' => (int) $c->cnt])->all(),
        ])
    </div>
@endsection
