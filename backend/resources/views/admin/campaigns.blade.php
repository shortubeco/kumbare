@extends('layouts.admin')
@php use App\Support\Format; use App\Support\Labels; @endphp
@section('title', 'Kampanyalar')

@section('content')
    <div class="p-head">
        <div>
            <h1>Kampanya moderasyonu</h1>
            <p>Şubelerin yayımladığı kampanya ve duyurular. Uygunsuz olanı gerekçesiyle yayından kaldırın; kayıt silinmez.</p>
        </div>
    </div>

    <nav class="tabs" aria-label="Filtre">
        <a href="{{ route('admin.campaigns', array_filter(['q' => $q])) }}" @if ($scope === 'running') aria-current="page" @endif>Yayında olanlar</a>
        <a href="{{ route('admin.campaigns', array_filter(['durum' => 'all', 'q' => $q])) }}" @if ($scope === 'all') aria-current="page" @endif>Tümü</a>
    </nav>

    <form class="filters" method="get" action="{{ route('admin.campaigns') }}" role="search">
        @if ($scope === 'all')<input type="hidden" name="durum" value="all">@endif
        <div class="field" style="flex:1 1 280px">
            <label for="q">Ara</label>
            <input id="q" type="search" name="q" value="{{ $q }}" placeholder="Kampanya başlığı, açıklama veya işletme adı">
        </div>
        <button class="btn primary" type="submit">Ara</button>
    </form>

    <div class="card flush">
        @if ($campaigns->isEmpty())
            <p class="empty">Kampanya bulunamadı.</p>
        @else
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th>Kampanya</th><th>Şube</th><th>Tür</th><th>Tarihler</th><th>Durum</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($campaigns as $c)
                        <tr>
                            <td style="max-width:360px">
                                <strong>{{ $c->title }}</strong>
                                @if ($c->description)<span class="sub">{{ \Illuminate\Support\Str::limit($c->description, 160) }}</span>@endif
                                @if ($c->terms)<span class="sub">Koşullar: {{ \Illuminate\Support\Str::limit($c->terms, 120) }}</span>@endif
                            </td>
                            <td><a href="{{ route('admin.businesses.show', $c->branch->business_id) }}">{{ $c->branch->business->name }} – {{ $c->branch->name }}</a></td>
                            <td>
                                @if ($c->type === 'rate_override')
                                    Oran {{ Format::rate($c->reward_rate) }}
                                @else
                                    Duyuru
                                @endif
                                @if ($c->birthday_only)<span class="sub">Doğum günü</span>@endif
                                @if ($c->new_customers_only)<span class="sub">Yeni müşteri</span>@endif
                            </td>
                            <td class="nowrap">{{ Labels::date($c->starts_at) }}<span class="sub">→ {{ Labels::date($c->ends_at) }}</span></td>
                            <td>{!! Labels::badge('campaign', $c->status) !!}</td>
                            <td>
                                @if ($c->status !== 'ended')
                                    @include('admin.partials.reason-action', [
                                        'action' => route('admin.campaigns.remove', $c), 'label' => 'Yayından kaldır', 'btn' => 'danger',
                                        'title' => 'Kaldırma gerekçesi', 'hint' => 'Gerekçe şube yöneticisine bildirilir.',
                                    ])
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @include('site.partials.pagination', ['paginator' => $campaigns])
        @endif
    </div>
@endsection
