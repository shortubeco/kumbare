@extends('layouts.admin')
@php use App\Support\Format; use App\Support\Labels; @endphp
@section('title', 'Müşteriler')

@section('content')
    <div class="p-head">
        <div>
            <h1>Müşteriler</h1>
            <p>Müşteri numarası, ad soyad, e-posta veya telefonla arayın. Bakiyeler buradan değiştirilemez.</p>
        </div>
    </div>

    <nav class="tabs" aria-label="Duruma göre">
        @foreach (['' => 'Tümü', 'active' => 'Aktif', 'passive' => 'Pasif', 'deleted' => 'Hesabını silen'] as $key => $label)
            <a href="{{ route('admin.customers', array_filter(['durum' => $key, 'q' => $q])) }}" @if ($status === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    <form class="filters" method="get" action="{{ route('admin.customers') }}" role="search">
        @if ($status !== '')<input type="hidden" name="durum" value="{{ $status }}">@endif
        <div class="field" style="flex:1 1 280px">
            <label for="q">Ara</label>
            <input id="q" type="search" name="q" value="{{ $q }}" placeholder="Örn. 125, Ayşe Yılmaz, ayse@… veya 0532…">
        </div>
        <button class="btn primary" type="submit">Ara</button>
        @if ($q !== '')<a class="btn" href="{{ route('admin.customers', array_filter(['durum' => $status])) }}">Temizle</a>@endif
    </form>

    <div class="card flush">
        @if ($customers->isEmpty())
            <p class="empty">Müşteri bulunamadı.</p>
        @else
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th class="n">No</th><th>Ad soyad</th><th>İletişim</th><th>Konum</th><th>Kayıt</th><th>Durum</th></tr></thead>
                    <tbody>
                    @foreach ($customers as $c)
                        <tr>
                            <td class="n num">{{ $c->customer_no }}</td>
                            <td><a class="row-link" href="{{ route('admin.customers.show', $c->id) }}">{{ $c->fullName() }}</a></td>
                            <td>{{ $c->email }}<span class="sub">{{ $c->phone }}</span></td>
                            <td>{{ $c->district?->name }}<span class="sub">{{ $c->city?->name }}</span></td>
                            <td class="nowrap">{{ Labels::date($c->created_at) }}</td>
                            <td>
                                @if ($c->trashed())
                                    <span class="badge">Hesap silindi</span>
                                @else
                                    {!! Labels::badge('customer', $c->status) !!}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @include('site.partials.pagination', ['paginator' => $customers])
        @endif
    </div>
@endsection
