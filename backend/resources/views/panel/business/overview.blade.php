@extends('layouts.panel')
@php
    use App\Support\Format; use App\Support\Labels;
    $active = collect($rows)->where('status', 'active');
    $pro = collect($rows)->filter(fn ($r) => $r->pro_until)->count();
    $sum = fn ($k) => collect($rows)->sum(fn ($r) => (float) $r->$k);
    $shortMonths = ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'];
@endphp
@section('title', 'Genel bakış')

@section('content')
    <div class="p-head">
        <div>
            <h1>{{ $business->name }}</h1>
            <p>{{ count($rows) }} şube · {{ $active->count() }} aktif · {{ Labels::dateTime(now()) }}</p>
        </div>
        <a class="btn gold" href="{{ route('biz.branches.create') }}">Şube ekle</a>
    </div>

    @if (empty($rows))
        <div class="card">
            <h2>İlk şubenizi ekleyin</h2>
            <p>Her şube bağımsız bir restoran gibi çalışır: kendi kumbarası, ödül oranı, aylık müşteri sayacı ve paketi olur. Şubeyi ekledikten sonra şube yöneticisi hesabını oluşturun; şube yöneticisi ödül oranını ve çalışanlarını belirler.</p>
            <ol class="steps">
                <li><a href="{{ route('biz.profile') }}">İşletme bilgilerini</a> ve logonuzu kontrol edin.</li>
                <li><a href="{{ route('biz.menu') }}">Menünüzü</a> ekleyin (tüm şubelerde aynı görünür).</li>
                <li><a href="{{ route('biz.branches.create') }}">Şube ekleyin</a> ve şube yöneticisini tanımlayın.</li>
            </ol>
        </div>
    @else
        <div class="stats three">
            <div class="stat"><div class="k">Toplam müşteri</div><div class="v">{{ Format::number($totalCustomers) }}</div><div class="s">en az bir şubede ödül kazanmış farklı kişi</div></div>
            <div class="stat"><div class="k">İşlem (son 30 gün)</div><div class="v">{{ Format::number((int) $sum('tx30')) }}</div><div class="s">tüm şubeler</div></div>
            <div class="stat"><div class="k">Paketler</div><div class="v">{{ $pro }} / {{ count($rows) }}</div><div class="s">şube Profesyonel pakette</div></div>
            <div class="stat gold"><div class="k">Kazandırılan ödül (30 gün)</div><div class="v">{{ Format::money($sum('earned30'), false) }}</div><div class="s">TL</div></div>
            <div class="stat gold"><div class="k">Kullanılan ödül (30 gün)</div><div class="v">{{ Format::money($sum('redeemed30'), false) }}</div><div class="s">TL</div></div>
            <div class="stat gold"><div class="k">Kumbaralardaki bakiye</div><div class="v">{{ Format::money($sum('balance'), false) }}</div><div class="s">TL · her şubenin bakiyesi yalnızca o şubede kullanılır</div></div>
        </div>

        <div class="card flush">
            <div class="card-head"><h2>Şubeler</h2><a class="small" href="{{ route('biz.reports') }}">Tarih aralığıyla karşılaştır →</a></div>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th>Şube</th><th class="n">Oran</th><th>Bu ay müşteri</th><th class="n">Müşteri</th><th class="n">İşlem (30g)</th><th class="n">Kazandırılan (30g)</th><th class="n">Bakiye</th><th>Puan</th><th>Durum</th></tr></thead>
                    <tbody>
                    @foreach ($rows as $r)
                        @php $used = (int) ($r->monthly ?? 0); $pct = $limit > 0 ? min(100, $used * 100 / $limit) : 0; @endphp
                        <tr>
                            <td><a class="row-link" href="{{ route('biz.branches.show', $r->id) }}">{{ $r->name }}</a>
                                <span class="sub">{{ $r->district }}, {{ $r->city }}@if (! $r->manager) · <span class="field-error">yönetici yok</span>@endif</span></td>
                            <td class="n">{{ Format::rate($r->reward_rate) }}</td>
                            <td>
                                @if ($r->pro_until)
                                    <span class="num">{{ Format::number($used) }}</span> <span class="badge pro">Pro</span>
                                @else
                                    <span class="num">{{ Format::number($used) }}</span><span class="muted"> / {{ Format::number($limit) }}</span>
                                    <div class="meter {{ $pct >= 100 ? 'full' : ($pct >= 80 ? 'warn' : '') }}"><span style="width: {{ $pct }}%"></span></div>
                                @endif
                            </td>
                            <td class="n">{{ Format::number((int) $r->customers) }}</td>
                            <td class="n">{{ Format::number((int) $r->tx30) }}</td>
                            <td class="n money">{{ Format::money($r->earned30) }}</td>
                            <td class="n money">{{ Format::money($r->balance) }}</td>
                            <td class="nowrap">@if ($r->rating_count > 0)<b>{{ Format::score($r->rating_avg) }}</b> <span class="muted small">({{ $r->rating_count }})</span>@else<span class="muted">—</span>@endif</td>
                            <td>{!! Labels::badge('branch', $r->status) !!}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="charts">
            @include('admin.partials.column-chart', [
                'title' => 'Ödüller (tüm şubeler)',
                'subtitle' => 'Son 6 ay · aylık toplam',
                'labels' => collect($monthly)->map(fn ($m) => $shortMonths[\Carbon\Carbon::parse($m->month)->month - 1])->all(),
                'tipLabels' => collect($monthly)->map(fn ($m) => Labels::month($m->month))->all(),
                'money' => true,
                'series' => [
                    ['name' => 'Kazandırılan', 'color' => '#00897b', 'values' => collect($monthly)->pluck('earned')->all()],
                    ['name' => 'Kullanılan', 'color' => '#b98600', 'values' => collect($monthly)->pluck('redeemed')->all()],
                ],
            ])
            @include('admin.partials.bar-list', [
                'title' => 'Şubelere göre kazandırılan ödül',
                'subtitle' => 'Son 30 gün',
                'color' => '#00897b', 'money' => true,
                'rows' => collect($rows)->sortByDesc(fn ($r) => (float) $r->earned30)->take(10)
                    ->map(fn ($r) => ['label' => $r->name, 'sub' => $r->district.', '.$r->city, 'value' => (float) $r->earned30, 'url' => route('biz.branches.show', $r->id)])->values()->all(),
            ])
        </div>
    @endif
@endsection
