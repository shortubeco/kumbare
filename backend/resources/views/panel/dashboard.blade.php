@extends('layouts.panel')
@php
    use App\Support\Format; use App\Support\Labels;
    $isPro = $counter['plan'] === 'pro';
    $limit = $counter['limit'];
    $used = $counter['unique_customers'];
    $pct = (! $isPro && $limit > 0) ? min(100, $used * 100 / $limit) : 0;
@endphp
@section('title', 'Genel bakış')

@section('content')
    <div class="p-head">
        <div>
            <h1>Genel bakış</h1>
            <p>{{ $branch->business->name }} – {{ $branch->name }} · {{ Labels::dateTime(now()) }}</p>
        </div>
        <a class="btn gold" href="{{ route('panel.tx.screen') }}">İşlem ekranına git</a>
    </div>

    {{-- Bu ayın müşteri sayısı ve paket --}}
    <div class="grid two" style="margin-bottom:18px">
        <div class="card" style="margin:0">
            <div class="card-head">
                <h2>Bu ay ödül kazandırılan farklı müşteri</h2>
                <span class="muted small">{{ Labels::month(now()) }}</span>
            </div>
            @if ($isPro)
                <p><span class="score num">{{ Format::number($used) }}</span> müşteri · <span class="badge pro">Profesyonel</span> sınır yok</p>
            @else
                <p><span class="score num">{{ Format::number($used) }} / {{ Format::number($limit) }}</span> müşteri</p>
                <div class="meter {{ $pct >= 100 ? 'full' : ($pct >= 80 ? 'warn' : '') }}" style="height:12px"><span style="width: {{ $pct }}%"></span></div>
                @if ($counter['limit_reached'])
                    <div class="flash err" style="margin:12px 0 0">Limit doldu: bu ay ilk kez gelen müşterilere ödül kazandırılamaz. <a href="{{ route('panel.package') }}">Profesyonel pakete geçin</a>.</div>
                @elseif ($pct >= 90)
                    <div class="flash err" style="margin:12px 0 0">Limitin %90'ına ulaştınız; {{ Format::number($limit - $used) }} müşteri kaldı.</div>
                @elseif ($pct >= 80)
                    <div class="flash info" style="margin:12px 0 0">Limitin %80'ine ulaştınız; {{ Format::number($limit - $used) }} müşteri kaldı.</div>
                @endif
            @endif
            <p class="muted small" style="margin:10px 0 0">Bu ay gelen müşteriler: <b>{{ Format::number((int) $returning->new) }}</b> yeni, <b>{{ Format::number((int) $returning->returning) }}</b> tekrar gelen.</p>
        </div>
        <div class="card" style="margin:0">
            <div class="card-head">
                <h2>Paket</h2>
                <a class="small" href="{{ route('panel.package') }}">Paket ve ödeme →</a>
            </div>
            @if ($subscription)
                <p><span class="badge pro">Profesyonel</span> {{ Labels::text('cycle', $subscription->billing_cycle) }} · {{ Labels::date($subscription->ends_at) }} tarihine kadar</p>
                @php $days = (int) ceil(now()->floatDiffInDays($subscription->ends_at)); @endphp
                <p class="{{ $days <= 7 ? 'field-error' : 'muted' }}">{{ $days }} gün kaldı{{ $days <= 7 ? ' — süre bitince Ücretsiz pakete dönülür.' : '' }}</p>
            @else
                <p><span class="badge">Ücretsiz</span> Ayda {{ Format::number($limit) }} farklı müşteriye kadar ödül.</p>
                <p class="muted small">Sınırsız müşteri için Profesyonel pakete geçebilirsiniz.</p>
            @endif
            @if ($pending > 0)
                <div class="flash info" style="margin:10px 0 0">{{ Format::number($pending) }} ödül kullanım/düzeltme isteği müşteri onayı bekliyor.</div>
            @endif
        </div>
    </div>

    <div class="stats three">
        <div class="stat"><div class="k">Toplam müşteri</div><div class="v">{{ Format::number((int) $wallets->customers) }}</div>
            <div class="s">bu şubede en az bir kez ödül kazanan</div></div>
        <div class="stat"><div class="k">Bugünkü işlem</div><div class="v">{{ Format::number((int) $tx->today) }}</div>
            <div class="s">son 7 gün {{ Format::number((int) $tx->week) }} · bu ay {{ Format::number((int) $tx->month) }}</div></div>
        <div class="stat"><div class="k">Bu ay</div><div class="v money" style="font-size:1.35rem">+{{ Format::money($tx->month_earned) }}</div>
            <div class="s">kazandırılan · kullanılan {{ Format::money($tx->month_redeemed) }}</div></div>
        <div class="stat gold"><div class="k">Toplam kazandırılan ödül</div><div class="v">{{ Format::money($wallets->earned, false) }}</div><div class="s">TL</div></div>
        <div class="stat gold"><div class="k">Toplam kullanılan ödül</div><div class="v">{{ Format::money($wallets->redeemed, false) }}</div><div class="s">TL</div></div>
        <div class="stat gold"><div class="k">Kumbaralardaki bakiye</div><div class="v">{{ Format::money($wallets->balance, false) }}</div><div class="s">TL · müşterilerin kullanabileceği ödül</div></div>
    </div>

    @php
        $shortMonths = ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'];
        $teal = '#00897b'; $amber = '#b98600';
    @endphp
    <div class="charts">
        @include('admin.partials.column-chart', [
            'title' => 'Günlük işlem sayısı',
            'subtitle' => 'Son 30 gün',
            'labels' => collect($daily)->map(fn ($r) => \Carbon\Carbon::parse($r->day)->format('j'))->all(),
            'tipLabels' => collect($daily)->map(fn ($r) => Labels::date($r->day))->all(),
            'every' => 5,
            'series' => [['name' => 'İşlem', 'color' => $teal, 'values' => collect($daily)->pluck('cnt')->all()]],
        ])
        @include('admin.partials.column-chart', [
            'title' => 'Ödüller',
            'subtitle' => 'Son 6 ay · aylık toplam',
            'labels' => collect($monthly)->map(fn ($r) => $shortMonths[\Carbon\Carbon::parse($r->month)->month - 1])->all(),
            'tipLabels' => collect($monthly)->map(fn ($r) => Labels::month($r->month))->all(),
            'money' => true,
            'series' => [
                ['name' => 'Kazandırılan', 'color' => $teal, 'values' => collect($monthly)->pluck('earned')->all()],
                ['name' => 'Kullanılan', 'color' => $amber, 'values' => collect($monthly)->pluck('redeemed')->all()],
            ],
        ])
    </div>

    <div class="card flush">
        <div class="card-head"><h2>En aktif müşteriler</h2><span class="muted small">Son 30 gün · işlem sayısına göre</span></div>
        @if (empty($topCustomers))
            <p class="empty">Son 30 günde işlem yok.</p>
        @else
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th class="n">No</th><th>Müşteri</th><th class="n">İşlem</th><th class="n">Kazandığı ödül</th><th class="n">Bakiye</th></tr></thead>
                    <tbody>
                    @foreach ($topCustomers as $c)
                        <tr>
                            <td class="n num">{{ $c->customer_no }}</td>
                            <td>{{ $c->first_name }} {{ $c->last_name }}</td>
                            <td class="n">{{ Format::number((int) $c->cnt) }}</td>
                            <td class="n money">{{ Format::money($c->earned) }}</td>
                            <td class="n money">{{ Format::money($c->balance) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
