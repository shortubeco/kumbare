@extends('layouts.panel')
@php use App\Support\Format; use App\Support\Labels; @endphp
@section('title', 'Abonelikler')

@section('content')
    <div class="p-head">
        <div>
            <h1>Abonelikler</h1>
            <p>Paket her şube için ayrıdır. Profesyonel pakete geçiş ve havale bildirimi şube yöneticisinin panelinden ("Paket ve ödeme") yapılır. Bu sayfa yalnızca bilgi içindir.</p>
        </div>
    </div>

    <div class="card flush">
        <h2>Şubelerin paketi</h2>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead><tr><th>Şube</th><th>Paket</th><th>Bitiş</th><th>Bu ay müşteri</th></tr></thead>
                <tbody>
                @foreach ($branches as $b)
                    @php $s = $current[$b->id] ?? null; $used = (int) ($monthly[$b->id] ?? 0); @endphp
                    <tr>
                        <td><a href="{{ route('biz.branches.show', $b->id) }}">{{ $b->name }}</a>@if ($b->status !== 'active') {!! Labels::badge('branch', $b->status) !!}@endif</td>
                        <td>@if ($s)<span class="badge pro">Profesyonel</span> <span class="small">{{ Labels::text('cycle', $s->billing_cycle) }}</span>@else<span class="badge">Ücretsiz</span>@endif</td>
                        <td class="nowrap">@if ($s){{ Labels::date($s->ends_at) }}<span class="sub">{{ (int) ceil(now()->floatDiffInDays($s->ends_at)) }} gün kaldı</span>@else<span class="muted">—</span>@endif</td>
                        <td>{{ Format::number($used) }}@unless ($s)<span class="muted"> / {{ Format::number($limit) }}</span>@endunless</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid two" style="align-items:start">
        <div class="card flush">
            <h2>Profesyonel dönemleri</h2>
            @if ($history->isEmpty())
                <p class="empty">Henüz Profesyonel paket alınmadı.</p>
            @else
                <div class="tbl-wrap">
                    <table class="tbl">
                        <thead><tr><th>Şube</th><th>Dönem</th><th class="n">Ücret</th><th>Durum</th></tr></thead>
                        <tbody>
                        @foreach ($history as $h)
                            <tr>
                                <td>{{ $h->branch->name }}</td>
                                <td class="small nowrap">{{ Labels::date($h->starts_at) }} – {{ Labels::date($h->ends_at) }}</td>
                                <td class="n money">{{ Format::money($h->price) }}</td>
                                <td>{!! Labels::badge('subscription', $h->status === 'active' && $h->ends_at->isPast() ? 'expired' : $h->status) !!}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        <div class="card flush">
            <h2>Havale bildirimleri</h2>
            @if ($payments->isEmpty())
                <p class="empty">Bildirim yok.</p>
            @else
                <div class="tbl-wrap">
                    <table class="tbl">
                        <thead><tr><th>Şube</th><th>Ne için</th><th class="n">Tutar</th><th>Durum</th></tr></thead>
                        <tbody>
                        @foreach ($payments as $p)
                            <tr>
                                <td>{{ $p->branch->name }}<span class="sub">{{ Labels::date($p->created_at) }}</span></td>
                                <td class="small">{{ Labels::text('purpose', $p->purpose) }} · {{ $p->purpose === 'subscription' ? Labels::text('cycle', $p->billing_cycle) : Labels::month($p->sponsorship_month) }}</td>
                                <td class="n money">{{ Format::money($p->amount) }}</td>
                                <td>{!! Labels::badge('payment', $p->status) !!}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
