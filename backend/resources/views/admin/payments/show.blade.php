@extends('layouts.admin')
@php use App\Support\Format; use App\Support\Labels; $branch = $payment->branch; @endphp
@section('title', 'Havale bildirimi #'.$payment->id)

@section('content')
    <div class="p-head">
        <div>
            <div class="crumb"><a href="{{ route('admin.payments') }}">← Havale bildirimleri</a></div>
            <h1>Havale bildirimi #{{ $payment->id }}</h1>
            <p>{!! Labels::badge('payment', $payment->status) !!} · {{ Labels::dateTime($payment->created_at) }} tarihinde gönderildi</p>
        </div>
    </div>

    <div class="grid two">
        <div class="card">
            <h2>Ödeme</h2>
            <dl class="kv">
                <dt>Amaç</dt>
                <dd>
                    {!! Labels::badge('purpose', $payment->purpose) !!}
                    {{ $payment->purpose === 'subscription' ? Labels::text('cycle', $payment->billing_cycle) : Labels::month($payment->sponsorship_month).' ayı' }}
                </dd>
                <dt>Tutar</dt><dd class="money">{{ Format::money($payment->amount) }}</dd>
                <dt>Beklenen tutar</dt>
                <dd>
                    @if ($expected !== null && $expected !== '')
                        <span class="money">{{ Format::money($expected) }}</span>
                        @if ((int) round((float) $payment->amount * 100) !== (int) round((float) $expected * 100))
                            <span class="badge warn">Tutar farklı</span>
                        @endif
                    @else
                        <span class="muted">Fiyat ayarlanmamış</span>
                    @endif
                </dd>
                <dt>Gönderen</dt><dd>{{ $payment->sender_name }}</dd>
                <dt>Havale tarihi</dt><dd>{{ Labels::date($payment->transfer_date) }}</dd>
                <dt>Açıklama</dt><dd>{{ $payment->description ?: '—' }}</dd>
                <dt>Dekont</dt>
                <dd>@if ($hasReceipt)<a href="{{ route('admin.payments.receipt', $payment) }}" target="_blank" rel="noopener">Dekontu aç ↗</a>@else<span class="muted">Eklenmemiş</span>@endif</dd>
            </dl>
        </div>

        <div class="card">
            <h2>Şube</h2>
            <dl class="kv">
                <dt>Şube</dt><dd><a href="{{ route('admin.businesses.show', $branch->business) }}">{{ $branch->business->name }} – {{ $branch->name }}</a></dd>
                <dt>Bildiren</dt><dd>{{ $payment->submitter?->fullName() }} <span class="muted">{{ $payment->submitter?->email }}</span></dd>
                <dt>Şu anki paket</dt>
                <dd>
                    @if ($activeSubscription)
                        <span class="badge pro">Profesyonel</span> {{ Labels::date($activeSubscription->ends_at) }} tarihine kadar
                        @if ($payment->purpose === 'subscription' && $payment->status === 'pending')
                            <span class="hint muted small" style="display:block">Onaylanırsa yeni dönem bu tarihten başlar; kalan gün kaybolmaz.</span>
                        @endif
                    @else
                        <span class="badge">Ücretsiz</span>
                        @if ($payment->purpose === 'subscription' && $payment->status === 'pending')
                            <span class="hint muted small" style="display:block">Onaylanırsa Profesyonel paket hemen başlar.</span>
                        @endif
                    @endif
                </dd>
                @if ($payment->status !== 'pending')
                    <dt>İnceleyen</dt><dd>{{ $payment->reviewer?->fullName() }} · {{ Labels::dateTime($payment->reviewed_at) }}</dd>
                    <dt>Not</dt><dd>{{ $payment->review_note ?: '—' }}</dd>
                @endif
            </dl>
        </div>
    </div>

    @if ($payment->status === 'pending')
        <div class="card">
            <h2>Karar</h2>
            <p class="muted">Banka hesabınızda bu havaleyi gördüyseniz onaylayın. Görmediyseniz veya tutar eksikse gerekçesiyle reddedin; şube yöneticisine bildirilir.</p>
            <div class="grid two">
                <form method="post" action="{{ route('admin.payments.approve', $payment) }}">
                    @csrf
                    <div class="field">
                        <label for="approve_note">Not <span class="muted">(isteğe bağlı)</span></label>
                        <input id="approve_note" type="text" name="note" maxlength="1000">
                    </div>
                    <button class="btn gold" type="submit">
                        {{ $payment->purpose === 'subscription' ? 'Onayla ve Profesyonel paketi başlat' : 'Onayla ve sponsorluğu başlat' }}
                    </button>
                </form>
                <form method="post" action="{{ route('admin.payments.reject', $payment) }}">
                    @csrf
                    <div class="field">
                        <label for="reject_note">Ret gerekçesi</label>
                        <input id="reject_note" type="text" name="note" maxlength="1000" required placeholder="Örn. havale hesaba ulaşmadı">
                    </div>
                    <button class="btn danger" type="submit">Reddet</button>
                </form>
            </div>
        </div>
    @endif

    @if ($history->isNotEmpty())
        <div class="card flush">
            <h2>Bu şubenin önceki bildirimleri</h2>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th>Bildirim</th><th>Amaç</th><th class="n">Tutar</th><th>Durum</th></tr></thead>
                    <tbody>
                    @foreach ($history as $h)
                        <tr>
                            <td><a href="{{ route('admin.payments.show', $h) }}">#{{ $h->id }}</a> <span class="sub">{{ Labels::date($h->created_at) }}</span></td>
                            <td>{{ Labels::text('purpose', $h->purpose) }} · {{ $h->purpose === 'subscription' ? Labels::text('cycle', $h->billing_cycle) : Labels::month($h->sponsorship_month) }}</td>
                            <td class="n money">{{ Format::money($h->amount) }}</td>
                            <td>{!! Labels::badge('payment', $h->status) !!}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
