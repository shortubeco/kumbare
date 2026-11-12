{{-- Kısa sıralama listesi (gerçek bir sıralama olduğu için numaralı).
     $metric: 'score' | 'reviews' | 'rate' | 'popular' — sağda büyük gösterilen değer --}}
<ol class="pano-liste">
    @foreach ($items as $b)
        <li>
            <a href="{{ route('web.branch', [$b->business->slug, $b->slug]) }}">
                <span class="pano-ad">
                    <strong>{{ $b->business->name }}</strong>
                    <span>{{ $b->name }}, {{ $b->district?->city?->name }}</span>
                </span>
                <span class="pano-deger">
                    @switch($metric)
                        @case('score')
                            <b>{{ \App\Support\Format::score($b->rating_avg) }}</b><small>{{ \App\Support\Format::number($b->rating_count) }} değ.</small>
                            @break
                        @case('reviews')
                            <b>{{ \App\Support\Format::number($b->rating_count) }}</b><small>değerlendirme</small>
                            @break
                        @case('rate')
                            <b class="odul-vurgu">{{ \App\Support\Format::rate($b->reward_rate) }}</b><small>ödül</small>
                            @break
                        @default
                            <b>{{ (float) $b->reward_rate > 0 ? \App\Support\Format::rate($b->reward_rate) : '–' }}</b><small>ödül</small>
                    @endswitch
                </span>
            </a>
        </li>
    @endforeach
</ol>
