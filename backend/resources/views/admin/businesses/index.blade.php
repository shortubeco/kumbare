@extends('layouts.admin')
@php use App\Support\Format; use App\Support\Labels; $isPending = $status === 'pending'; @endphp
@section('title', $isPending ? 'Başvurular' : 'İşletmeler')

@section('content')
    <div class="p-head">
        <div>
            <h1>{{ $isPending ? 'İşletme başvuruları' : 'İşletmeler ve şubeler' }}</h1>
            <p>{{ $isPending ? 'En eski başvuru en üstte. Onaylanan işletmenin yöneticisi panele giriş yapabilir.' : 'İşletmeyi açarak şubelerini, paketlerini ve aylık müşteri sayılarını görün.' }}</p>
        </div>
    </div>

    <nav class="tabs" aria-label="Duruma göre">
        <a href="{{ route('admin.businesses', array_filter(['q' => $q])) }}" @if ($status === '') aria-current="page" @endif>Tümü<span class="c">{{ Format::number($counts->sum()) }}</span></a>
        @foreach ($statuses as $key => $label)
            <a href="{{ route('admin.businesses', array_filter(['durum' => $key, 'q' => $q])) }}" @if ($status === $key) aria-current="page" @endif>{{ $label }}<span class="c">{{ Format::number($counts[$key] ?? 0) }}</span></a>
        @endforeach
    </nav>

    <form class="filters" method="get" action="{{ route('admin.businesses') }}" role="search">
        @if ($status !== '')<input type="hidden" name="durum" value="{{ $status }}">@endif
        <div class="field" style="flex:1 1 280px">
            <label for="q">Ara</label>
            <input id="q" type="search" name="q" value="{{ $q }}" placeholder="İşletme adı, ticari unvan, vergi no veya yönetici e-postası">
        </div>
        <button class="btn primary" type="submit">Ara</button>
        @if ($q !== '')<a class="btn" href="{{ route('admin.businesses', array_filter(['durum' => $status])) }}">Temizle</a>@endif
    </form>

    <div class="card flush">
        @if ($businesses->isEmpty())
            <p class="empty">{{ $isPending ? 'Bekleyen başvuru yok.' : 'Kayıt bulunamadı.' }}</p>
        @else
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                    <tr>
                        <th>İşletme</th>
                        <th>Yönetici</th>
                        <th>Mutfak</th>
                        <th class="n">Şube</th>
                        <th>Durum</th>
                        <th>{{ $isPending ? 'Başvuru' : 'Kayıt' }}</th>
                        @if ($isPending)<th></th>@endif
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($businesses as $b)
                        <tr>
                            <td>
                                <a class="row-link" href="{{ route('admin.businesses.show', $b) }}">{{ $b->name }}</a>
                                <span class="sub">{{ $b->legal_name }}@if ($b->tax_number) · VKN {{ $b->tax_number }}@endif</span>
                            </td>
                            <td>
                                @if ($b->owner)
                                    {{ $b->owner->fullName() }}
                                    <span class="sub">{{ $b->owner->email }} · {{ $b->owner->phone }}</span>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td>{{ $b->category?->name ?? '—' }}</td>
                            <td class="n">{{ Format::number($b->branches_count) }}</td>
                            <td>{!! Labels::badge('business', $b->status) !!}</td>
                            <td class="nowrap">{{ Labels::date($b->created_at) }}</td>
                            @if ($isPending)
                                <td class="nowrap">
                                    <div class="actions">
                                        <form method="post" action="{{ route('admin.businesses.approve', $b) }}">
                                            @csrf
                                            <button class="btn sm gold" type="submit">Onayla</button>
                                        </form>
                                        @include('admin.partials.reason-action', [
                                            'action' => route('admin.businesses.reject', $b),
                                            'label' => 'Reddet', 'btn' => 'danger',
                                            'title' => 'Ret gerekçesi', 'hint' => 'Gerekçe işletmeye e-postayla iletilir.',
                                        ])
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @include('site.partials.pagination', ['paginator' => $businesses])
        @endif
    </div>
@endsection
