@extends('layouts.panel')
@php use App\Support\Format; use App\Support\Labels; @endphp
@section('title', 'Müşteriler')

@section('content')
    <div class="p-head">
        <div>
            <h1>Müşteriler</h1>
            <p>Şubenizde en az bir kez işlem yapmış müşteriler: {{ Format::number((int) $summary->customers) }} müşteri,
               toplam bakiye <b class="money">{{ Format::money($summary->balance) }}</b>.</p>
        </div>
    </div>

    <form class="filters" method="get" action="{{ route('panel.customers') }}" role="search">
        <div class="field" style="flex:1 1 260px">
            <label for="q">Ara</label>
            <input id="q" type="search" name="q" value="{{ $q }}" placeholder="Müşteri numarası veya ad soyad">
        </div>
        <div class="field">
            <label for="siralama">Sıralama</label>
            <select id="siralama" name="siralama" onchange="this.form.submit()">
                @foreach (['son' => 'Son işleme göre', 'bakiye' => 'Bakiyeye göre', 'kazanilan' => 'Kazanılan ödüle göre', 'islem' => 'İşlem sayısına göre', 'no' => 'Müşteri numarasına göre'] as $k => $label)
                    <option value="{{ $k }}" @selected($sort === $k)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn primary" type="submit">Ara</button>
        @if ($q !== '')<a class="btn" href="{{ route('panel.customers', ['siralama' => $sort]) }}">Temizle</a>@endif
    </form>

    <div class="card flush">
        @if ($customers->isEmpty())
            <p class="empty">{{ $q !== '' ? 'Aramaya uyan müşteri yok.' : 'Henüz müşteri yok.' }}</p>
        @else
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th class="n">No</th><th>Müşteri</th><th class="n">İşlem</th><th class="n">Kazanılan</th><th class="n">Kullanılan</th><th class="n">Bakiye</th><th>Son işlem</th></tr></thead>
                    <tbody>
                    @foreach ($customers as $c)
                        <tr>
                            <td class="n num">{{ $c->customer_no }}</td>
                            <td>
                                <a class="row-link" href="{{ route('panel.customers.show', $c->customer_no) }}">{{ $c->first_name }} {{ $c->last_name }}</a>
                                @if ($c->deleted_at)<span class="sub">Hesabını sildi</span>@elseif ($c->status === 'passive')<span class="sub">Hesap pasif</span>@endif
                            </td>
                            <td class="n">{{ Format::number((int) $c->tx_count) }}</td>
                            <td class="n money">{{ Format::money($c->total_earned) }}</td>
                            <td class="n money">{{ Format::money($c->total_redeemed) }}</td>
                            <td class="n money">{{ Format::money($c->balance) }}</td>
                            <td class="nowrap">{{ $c->last_tx ? Labels::dateTime($c->last_tx) : '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @include('site.partials.pagination', ['paginator' => $customers])
        @endif
    </div>
@endsection
