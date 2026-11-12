@extends('layouts.panel')
@php
    use App\Support\Format; use App\Support\Labels;
    $q = $preset === 'ozel' ? ['baslangic' => $from->toDateString(), 'bitis' => $to->toDateString()] : ['aralik' => $preset];
@endphp
@section('title', 'Raporlar')

@section('content')
    <div class="p-head">
        <div>
            <h1>Raporlar</h1>
            <p>Şube karşılaştırması · {{ Labels::date($from) }} – {{ Labels::date($to) }} · {{ Format::number($from->diffInDays($to) + 1) }} gün</p>
        </div>
        <a class="btn" href="{{ route('biz.reports', $q + ['indir' => 'subeler']) }}">CSV indir</a>
    </div>

    <nav class="tabs" aria-label="Tarih aralığı">
        @foreach ($presets as $k => $label)
            <a href="{{ route('biz.reports', ['aralik' => $k]) }}" @if ($preset === $k) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <form class="filters" method="get" action="{{ route('biz.reports') }}">
        <div class="field"><label for="baslangic">Başlangıç</label><input id="baslangic" type="date" name="baslangic" value="{{ $from->toDateString() }}" max="{{ now()->toDateString() }}"></div>
        <div class="field"><label for="bitis">Bitiş</label><input id="bitis" type="date" name="bitis" value="{{ $to->toDateString() }}" max="{{ now()->toDateString() }}"></div>
        <button class="btn primary" type="submit">Göster</button>
    </form>
    @if ($rangeError)<div class="flash err">{{ $rangeError }} Son 30 gün gösteriliyor.</div>@endif

    <div class="stats three">
        <div class="stat"><div class="k">Farklı müşteri</div><div class="v">{{ Format::number($totals->customers) }}</div><div class="s">{{ Format::number($totals->new_customers) }} şubede ilk kez</div></div>
        <div class="stat"><div class="k">İşlem</div><div class="v">{{ Format::number($totals->tx) }}</div><div class="s">tüm şubeler</div></div>
        <div class="stat"><div class="k">Ödüllü harcama</div><div class="v money" style="font-size:1.45rem">{{ Format::money($totals->spend) }}</div></div>
        <div class="stat gold"><div class="k">Kazandırılan ödül</div><div class="v">{{ Format::money($totals->earned, false) }}</div><div class="s">TL</div></div>
        <div class="stat gold"><div class="k">Kullanılan ödül</div><div class="v">{{ Format::money($totals->redeemed, false) }}</div><div class="s">TL</div></div>
        <div class="stat gold"><div class="k">Kullanılabilir bakiye</div><div class="v">{{ Format::money($totals->balance, false) }}</div><div class="s">TL · bugün</div></div>
    </div>

    <div class="card flush">
        <h2>Şubeler</h2>
        @if (empty($rows))
            <p class="empty">Şube yok.</p>
        @else
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th>Şube</th><th class="n">İşlem</th><th class="n">Müşteri</th><th class="n">Yeni</th><th class="n">Harcama</th><th class="n">Kazandırılan</th><th class="n">Kullanılan</th><th class="n">Bakiye</th></tr></thead>
                    <tbody>
                    @foreach ($rows as $r)
                        <tr>
                            <td><a href="{{ route('biz.branches.show', $r->id) }}">{{ $r->name }}</a>@if ($r->status !== 'active') {!! Labels::badge('branch', $r->status) !!}@endif</td>
                            <td class="n">{{ Format::number((int) $r->tx) }}</td>
                            <td class="n">{{ Format::number((int) $r->customers) }}</td>
                            <td class="n">{{ Format::number((int) $r->new_customers) }}</td>
                            <td class="n money">{{ Format::money($r->spend) }}</td>
                            <td class="n money">{{ Format::money($r->earned) }}</td>
                            <td class="n money">{{ Format::money($r->redeemed) }}</td>
                            <td class="n money">{{ Format::money($r->balance) }}</td>
                        </tr>
                    @endforeach
                    <tr style="font-weight:700;border-top:2px solid var(--cizgi)">
                        <td>Toplam</td>
                        <td class="n">{{ Format::number($totals->tx) }}</td>
                        <td class="n">{{ Format::number($totals->customers) }}</td>
                        <td class="n">{{ Format::number($totals->new_customers) }}</td>
                        <td class="n money">{{ Format::money($totals->spend) }}</td>
                        <td class="n money">{{ Format::money($totals->earned) }}</td>
                        <td class="n money">{{ Format::money($totals->redeemed) }}</td>
                        <td class="n money">{{ Format::money($totals->balance) }}</td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <p class="muted small" style="padding:0 20px 14px">Toplam müşteri, birden fazla şubeye gelen kişiyi bir kez sayar; bu yüzden şube müşterilerinin toplamından küçük olabilir. Şube bazında ayrıntılı rapor (günlük döküm, kampanyalar, en aktif müşteriler) şube yöneticisinin panelindedir.</p>
        @endif
    </div>

    @if (count($rows) > 1)
        <div class="charts">
            @include('admin.partials.bar-list', [
                'title' => 'Kazandırılan ödül', 'subtitle' => 'Şubelere göre',
                'color' => '#00897b', 'money' => true,
                'rows' => collect($rows)->map(fn ($r) => ['label' => $r->name, 'value' => (float) $r->earned, 'url' => route('biz.branches.show', $r->id)])->sortByDesc('value')->values()->all(),
            ])
            @include('admin.partials.bar-list', [
                'title' => 'Farklı müşteri', 'subtitle' => 'Şubelere göre',
                'color' => '#b98600', 'unit' => 'müşteri',
                'rows' => collect($rows)->map(fn ($r) => ['label' => $r->name, 'value' => (int) $r->customers, 'url' => route('biz.branches.show', $r->id)])->sortByDesc('value')->values()->all(),
            ])
        </div>
    @endif
@endsection
