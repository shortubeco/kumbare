{{--
    Yatay çubuk sıralaması (HTML/CSS). Uzun adlar için sütun grafiğinden okunaklıdır.
    Parametreler:
      $title, $subtitle (isteğe bağlı)
      $rows   [['label' => 'Közbaşı Kebap – Kadıköy', 'value' => 123, 'url' => '…' (isteğe bağlı), 'sub' => '…' (isteğe bağlı)], …]
      $color  Çubuk rengi
      $money  true: değerler TL
      $unit   Değerin yanına yazılacak birim (ör. "işlem")
--}}
@php
    use App\Support\Format;
    $subtitle ??= null;
    $money ??= false;
    $unit ??= '';
    $max = max(1, collect($rows)->max('value') ?? 0);
    $fmt = fn ($v) => $money ? Format::money($v) : Format::number((int) $v).($unit ? ' '.$unit : '');
@endphp
<figure class="chart">
    <figcaption>
        <h2>{{ $title }}</h2>
        @if ($subtitle)<span class="muted small">{{ $subtitle }}</span>@endif
    </figcaption>
    @if (empty($rows))
        <p class="muted chart-empty">Bu dönemde veri yok.</p>
    @else
        <ol class="bar-list">
            @foreach ($rows as $r)
                <li>
                    <span class="bl-label" title="{{ $r['label'] }}{{ ! empty($r['sub']) ? ' · '.$r['sub'] : '' }}">
                        @if (! empty($r['url']))<a href="{{ $r['url'] }}">{{ $r['label'] }}</a>@else{{ $r['label'] }}@endif
                        @if (! empty($r['sub']))<small>{{ $r['sub'] }}</small>@endif
                    </span>
                    <span class="bl-track" aria-hidden="true"><span style="width: {{ max(1, $r['value'] * 100 / $max) }}%; background: {{ $color }}"></span></span>
                    <span class="bl-value">{{ $fmt($r['value']) }}</span>
                </li>
            @endforeach
        </ol>
    @endif
</figure>
