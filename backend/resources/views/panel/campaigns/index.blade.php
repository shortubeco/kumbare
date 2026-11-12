@extends('layouts.panel')
@php use App\Support\Format; use App\Support\Labels; @endphp
@section('title', 'Kampanyalar')

@section('content')
    <div class="p-head">
        <div>
            <h1>Kampanyalar</h1>
            <p>Oran kampanyaları işlem ekranında seçilebilir (oranlar toplanmaz). Duyurular yalnızca bilgilendirir. Kampanyalar sitede ve uygulamada, ilçenizdeki müşterilere gösterilir.</p>
        </div>
        <a class="btn gold" href="{{ route('panel.campaigns.create') }}">Yeni kampanya</a>
    </div>

    <nav class="tabs" aria-label="Duruma göre">
        @foreach (['yayinda' => 'Yayında', 'yaklasan' => 'Başlayacak', 'taslak' => 'Taslak', 'biten' => 'Sona eren'] as $k => $label)
            <a href="{{ route('panel.campaigns', ['durum' => $k]) }}" @if ($tab === $k) aria-current="page" @endif>{{ $label }}<span class="c">{{ Format::number((int) $counts->$k) }}</span></a>
        @endforeach
    </nav>

    <div class="card flush">
        @if ($campaigns->isEmpty())
            <p class="empty">
                @if ($tab === 'yayinda')Şu an yayında kampanya yok. <a href="{{ route('panel.campaigns.create') }}">İlk kampanyanızı oluşturun</a>.@else Kayıt yok.@endif
            </p>
        @else
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th></th><th>Kampanya</th><th>Tür</th><th>Koşullar</th><th>Tarihler</th><th class="n">Kullanım</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($campaigns as $c)
                        @php
                            $u = $usage[$c->id] ?? null;
                            $days = collect($c->weekdayList())->map(fn ($d) => mb_substr(Format::DAYS[$d], 0, 3))->implode(', ');
                            $editable = $c->status !== 'ended' && $c->ends_at->isFuture();
                        @endphp
                        <tr>
                            <td style="width:80px">@if ($c->image_path)<img class="camp-img" src="{{ \App\Http\Resources\Support::fileUrl($c->image_path) }}" alt="">@endif</td>
                            <td style="max-width:300px">
                                <strong>{{ $c->title }}</strong>
                                @if ($c->description)<span class="sub">{{ \Illuminate\Support\Str::limit($c->description, 120) }}</span>@endif
                                @if ($c->status === 'draft')<span class="badge">Taslak</span>@endif
                            </td>
                            <td>{{ $c->type === 'rate_override' ? 'Oran '.Format::rate($c->reward_rate) : 'Duyuru' }}</td>
                            <td class="small">
                                {{ $days ?: 'Her gün' }}
                                @if ($c->new_customers_only)<span class="sub">Yalnızca yeni müşteriler</span>@endif
                                @if ($c->birthday_only)<span class="sub">Yalnızca doğum gününde</span>@endif
                            </td>
                            <td class="nowrap">{{ Labels::date($c->starts_at) }}<span class="sub">→ {{ Labels::date($c->ends_at->copy()->subSecond()) }}</span></td>
                            <td class="n">@if ($u){{ Format::number((int) $u->cnt) }} işlem<span class="sub">{{ Format::money($u->total) }} ödül</span>@else<span class="muted">—</span>@endif</td>
                            <td class="nowrap">
                                <div class="actions">
                                    @if ($editable)
                                        <a class="btn sm" href="{{ route('panel.campaigns.edit', $c->id) }}">Düzenle</a>
                                        <details class="act">
                                            <summary class="btn sm danger">Sona erdir</summary>
                                            <div class="pop">
                                                <p>"{{ $c->title }}" hemen sona erer ve işlem ekranında seçilemez. Bu işlem geri alınamaz.</p>
                                                <form method="post" action="{{ route('panel.campaigns.end', $c->id) }}">@csrf<button class="btn sm danger solid" type="submit">Evet, sona erdir</button></form>
                                            </div>
                                        </details>
                                    @endif
                                </div>
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
