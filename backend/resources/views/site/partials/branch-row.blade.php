@php
    /** @var \App\Models\Branch $b */
    $business = $b->business;
    $rate = (float) $b->reward_rate;
@endphp
<li class="restoran">
    <div class="tabela tabela-sm tabela-{{ \App\Support\Tabela::tone($business->category_id) }}" aria-hidden="true">{{ \App\Support\Tabela::initials($business->name) }}</div>
    <div>
        <h3><a href="{{ route('web.branch', [$business->slug, $b->slug]) }}">{{ $business->name }}<span class="sube-adi"><span class="visually-hidden"> – </span>{{ $b->name }}</span></a></h3>
        <div class="meta">
            @include('site.partials.score', ['avg' => $b->rating_avg, 'count' => $b->rating_count])
            <span>{{ $business->category?->name }}</span>
            <span>{{ $b->district?->displayName() }}, {{ $b->district?->city?->name }}</span>
            @isset($b->distance_km)
                <span class="num">{{ number_format((float) $b->distance_km, 1, ',', '') }} km</span>
            @endisset
        </div>
        @if ($b->is_sponsored || $b->has_campaign)
            <div class="tags">
                @if ($b->is_sponsored)<span class="tag tag-sponsor">Sponsorlu</span>@endif
                @if ($b->has_campaign)<span class="tag tag-campaign">Kampanya var</span>@endif
            </div>
        @endif
    </div>
    <div class="odul">
        @if ($rate > 0)
            <span class="reward">{{ \App\Support\Format::rate($b->reward_rate) }}</span>
            <small>ödül</small>
        @else
            <span class="reward-none">Ödül yok</span>
        @endif
    </div>
</li>
