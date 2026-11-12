@extends('layouts.panel')
@php use App\Support\Format; use App\Support\Labels; $isPro = $status['plan'] === 'pro'; @endphp
@section('title', 'Paket ve ödeme')

@section('content')
    <div class="p-head">
        <div>
            <h1>Paket ve ödeme</h1>
            <p>Paket şube bazındadır: bu sayfadaki bilgiler yalnızca <b>{{ $branch->name }}</b> şubesi içindir.</p>
        </div>
        @unless ($pendingSubscription)
            <a class="btn gold" href="{{ route('panel.payment.create') }}">Profesyonel'e geç / yenile</a>
        @endunless
    </div>

    {{-- Bu ayki müşteri sayısı --}}
    <div class="card">
        <div class="card-head">
            <h2>Bu ay ödül kazandırılan farklı müşteri</h2>
            <span class="muted small">{{ Labels::month(now()) }}</span>
        </div>
        @if ($isPro)
            <p class="stat-line"><span class="score num">{{ Format::number($status['unique_customers']) }}</span> müşteri · <span class="badge pro">Profesyonel</span> sınırsız</p>
        @else
            @php
                $pct = $status['limit'] > 0 ? min(100, $status['unique_customers'] * 100 / $status['limit']) : 100;
                $left = max(0, $status['limit'] - $status['unique_customers']);
            @endphp
            <p class="stat-line"><span class="score num">{{ Format::number($status['unique_customers']) }} / {{ Format::number($status['limit']) }}</span> müşteri</p>
            <div class="meter {{ $pct >= 100 ? 'full' : ($pct >= 80 ? 'warn' : '') }}" style="height:12px"><span style="width: {{ $pct }}%"></span></div>
            @if ($status['limit_reached'])
                <div class="flash err" style="margin:12px 0 0">Bu ayın ücretsiz müşteri limiti doldu. Bu ay daha önce ödül kazanmamış <b>yeni</b> müşterilere ödül kazandıramazsınız; bu ay gelmiş müşterilere ve ödül kullanımına devam edebilirsiniz. Sınırsız müşteri için Profesyonel pakete geçin.</div>
            @elseif ($pct >= 80)
                <div class="flash info" style="margin:12px 0 0">Limitin dolmasına {{ Format::number($left) }} müşteri kaldı.</div>
            @else
                <p class="muted small" style="margin:8px 0 0">Bu ay {{ Format::number($left) }} yeni müşteriye daha ödül kazandırabilirsiniz. Sayaç her ayın 1'inde sıfırlanır.</p>
            @endif
        @endif
    </div>

    {{-- Paketler --}}
    <div class="plan-card" style="margin-bottom:18px">
        <div @class(['plan', 'current' => ! $isPro])>
            @unless ($isPro)<span class="tag">Şu anki paketiniz</span>@endunless
            <h3>{{ $free?->name ?? 'Ücretsiz' }}</h3>
            <div class="price">0 TL</div>
            <p class="muted">{{ $free?->description }}</p>
            <ul>
                <li>Ayda {{ Format::number($status['limit'] ?? \App\Models\Setting::getInt('free_monthly_customer_limit', 100)) }} farklı müşteriye ödül</li>
                <li>Tüm özellikler: menü, kampanya, raporlar</li>
            </ul>
        </div>
        <div @class(['plan', 'pro', 'current' => $isPro])>
            @if ($isPro)<span class="tag">Şu anki paketiniz</span>@endif
            <h3>{{ $pro?->name ?? 'Profesyonel' }}</h3>
            @if ($pro?->monthly_price || $pro?->yearly_price)
                <div class="price">
                    @if ($pro->monthly_price){{ Format::money($pro->monthly_price) }} <small>/ ay</small>@endif
                    @if ($pro->monthly_price && $pro->yearly_price)<br>@endif
                    @if ($pro->yearly_price){{ Format::money($pro->yearly_price) }} <small>/ yıl</small>@endif
                </div>
            @else
                <div class="price"><small>Fiyat yakında açıklanacak</small></div>
            @endif
            <p class="muted">{{ $pro?->description }}</p>
            <ul>
                <li><b>Sınırsız</b> aylık müşteri</li>
                <li>Ödeme havale/EFT ile; onaylanınca hemen başlar</li>
                <li>Süre bitince yenilenmezse otomatik Ücretsiz pakete dönülür</li>
            </ul>
            @if ($subscription)
                <p><b>{{ Labels::date($subscription->ends_at) }}</b> tarihine kadar geçerli
                    ({{ (int) ceil(now()->floatDiffInDays($subscription->ends_at)) }} gün kaldı).</p>
            @endif
            @foreach ($queued as $q)
                <p class="small">Sıradaki dönem: {{ Labels::date($q->starts_at) }} – {{ Labels::date($q->ends_at) }} ({{ Labels::text('cycle', $q->billing_cycle) }})</p>
            @endforeach
            @if ($pendingSubscription)
                <div class="flash info" style="margin:0">Paket için gönderdiğiniz havale bildirimi onay bekliyor.</div>
            @else
                <div class="actions">
                    <a class="btn primary" href="{{ route('panel.payment.create', ['donem' => 'aylik']) }}">Aylık için havale bildir</a>
                    <a class="btn" href="{{ route('panel.payment.create', ['donem' => 'yillik']) }}">Yıllık için havale bildir</a>
                </div>
            @endif
        </div>
    </div>

    <div class="grid two">
        @include('panel.billing._bank')
        <div class="card">
            <h2>Nasıl ödenir?</h2>
            <ol class="steps">
                <li>Yandaki hesaba paket tutarını havale/EFT ile gönderin.</li>
                <li><a href="{{ route('panel.payment.create') }}">Havale bildirimi</a> formunu doldurun; varsa dekontu ekleyin.</li>
                <li>Kumbare ekibi havaleyi kontrol edip onaylayınca paket hemen başlar. Paketiniz devam ediyorsa yeni dönem bitiş tarihinden başlar, kalan gün kaybolmaz.</li>
            </ol>
        </div>
    </div>

    @include('panel.billing._history', ['history' => $history])
@endsection
