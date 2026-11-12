@extends('layouts.admin')
@php use App\Support\Format; use App\Support\Labels; @endphp
@section('title', 'Havale bildirimleri')

@section('content')
    <div class="p-head">
        <div>
            <h1>Havale bildirimleri</h1>
            <p>Hesaba gelen havaleyi kontrol edip onaylayın. Onaylanınca Profesyonel paket veya sponsorluk otomatik başlar.</p>
        </div>
    </div>

    <nav class="tabs" aria-label="Duruma göre">
        @foreach (['pending' => 'Bekleyen', 'approved' => 'Onaylanan', 'rejected' => 'Reddedilen', 'all' => 'Tümü'] as $key => $label)
            <a href="{{ route('admin.payments', ['durum' => $key]) }}" @if ($status === $key) aria-current="page" @endif>{{ $label }}
                @if ($key !== 'all')<span class="c">{{ Format::number($counts[$key] ?? 0) }}</span>@endif
            </a>
        @endforeach
    </nav>

    <div class="card flush">
        @if ($payments->isEmpty())
            <p class="empty">{{ $status === 'pending' ? 'Bekleyen havale bildirimi yok.' : 'Kayıt yok.' }}</p>
        @else
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th>Bildirim</th><th>Şube</th><th>Amaç</th><th class="n">Tutar</th><th>Gönderen</th><th>Havale tarihi</th><th>Durum</th></tr></thead>
                    <tbody>
                    @foreach ($payments as $p)
                        <tr>
                            <td class="nowrap"><a class="row-link" href="{{ route('admin.payments.show', $p) }}">#{{ $p->id }}</a><span class="sub">{{ Labels::dateTime($p->created_at) }}</span></td>
                            <td>{{ $p->branch->business->name }} – {{ $p->branch->name }}</td>
                            <td>
                                {!! Labels::badge('purpose', $p->purpose) !!}
                                <span class="sub">{{ $p->purpose === 'subscription' ? Labels::text('cycle', $p->billing_cycle) : Labels::month($p->sponsorship_month) }}</span>
                            </td>
                            <td class="n money">{{ Format::money($p->amount) }}</td>
                            <td>{{ $p->sender_name }}</td>
                            <td class="nowrap">{{ Labels::date($p->transfer_date) }}</td>
                            <td>{!! Labels::badge('payment', $p->status) !!}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @include('site.partials.pagination', ['paginator' => $payments])
        @endif
    </div>
@endsection
