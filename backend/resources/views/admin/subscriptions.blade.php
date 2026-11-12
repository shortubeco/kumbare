@extends('layouts.admin')
@php use App\Support\Format; use App\Support\Labels; @endphp
@section('title', 'Abonelikler')

@section('content')
    <div class="p-head">
        <div>
            <h1>Abonelikler</h1>
            <p>Profesyonel paket dönemleri. Süresi dolan şube otomatik olarak Ücretsiz pakete döner.</p>
        </div>
    </div>

    <nav class="tabs" aria-label="Filtre">
        @foreach (['active' => 'Aktif', 'expiring' => '30 gün içinde bitecek', 'expired' => 'Süresi dolan', 'cancelled' => 'İptal edilen', 'all' => 'Tümü'] as $key => $label)
            <a href="{{ route('admin.subscriptions', ['durum' => $key]) }}" @if ($filter === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    <div class="card flush">
        @if ($subscriptions->isEmpty())
            <p class="empty">Kayıt yok.</p>
        @else
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th>Şube</th><th>Dönem</th><th>Başlangıç</th><th>Bitiş</th><th class="n">Ücret</th><th>Durum</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($subscriptions as $s)
                        @php
                            $live = $s->status === 'active' && $s->ends_at->isFuture();
                            $daysLeft = $live ? (int) ceil(now()->floatDiffInDays($s->ends_at)) : null;
                        @endphp
                        <tr>
                            <td><a href="{{ route('admin.businesses.show', $s->branch->business_id) }}">{{ $s->branch->business->name }} – {{ $s->branch->name }}</a></td>
                            <td>{{ Labels::text('cycle', $s->billing_cycle) }}</td>
                            <td class="nowrap">{{ Labels::date($s->starts_at) }}@if ($s->starts_at->isFuture()) <span class="badge info">Sırada</span>@endif</td>
                            <td class="nowrap">{{ Labels::date($s->ends_at) }}
                                @if ($live)<span class="sub">{{ $daysLeft }} gün kaldı</span>@endif
                            </td>
                            <td class="n money">{{ Format::money($s->price) }}</td>
                            <td>
                                @if ($s->status === 'active' && ! $live)
                                    {!! Labels::badge('subscription', 'expired') !!}
                                @else
                                    {!! Labels::badge('subscription', $s->status) !!}
                                @endif
                                @if ($s->payment_notification_id)<span class="sub"><a href="{{ route('admin.payments.show', $s->payment_notification_id) }}">Havale #{{ $s->payment_notification_id }}</a></span>@endif
                            </td>
                            <td>
                                @if ($live)
                                    @include('admin.partials.reason-action', [
                                        'action' => route('admin.subscriptions.cancel', $s), 'label' => 'İptal et', 'btn' => 'danger',
                                        'title' => 'İptal gerekçesi', 'hint' => 'Yalnızca hatalı aktivasyonu geri almak için kullanın. Şube hemen Ücretsiz pakete döner; şube yöneticisine bildirilir.',
                                    ])
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @include('site.partials.pagination', ['paginator' => $subscriptions])
        @endif
    </div>
@endsection
