{{--
    Sunucuda çizilen sütun grafiği (SVG). JavaScript olmadan da görünür; üzerine gelince
    panel.js değerleri gösterir. Her grafiğin altında aynı veriler tablo olarak durur.

    Parametreler:
      $title     Başlık
      $subtitle  Alt başlık (isteğe bağlı)
      $labels    Eksen etiketleri (kısa)         ör. ['Haz', 'Tem', …]
      $tipLabels Açılır kutu etiketleri (uzun)   ör. ['Haziran 2026', …]  (verilmezse $labels)
      $series    [['name' => 'Kazandırılan', 'color' => '#00897b', 'values' => [..]], …]
      $stacked   true: üst üste, false: yan yana
      $money     true: değerler TL
      $every     Eksende her kaçıncı etiket yazılsın (varsayılan 1)
--}}
@php
    use App\Support\Format;

    $tipLabels ??= $labels;
    $stacked ??= false;
    $money ??= false;
    $every ??= 1;
    $subtitle ??= null;

    $fmt = fn ($v) => $money ? Format::money($v) : Format::number((int) round($v));
    $axis = function ($v) use ($money) {
        if ($v >= 1000000) { return rtrim(rtrim(number_format($v / 1000000, 1, ',', ''), '0'), ',').' Mn'; }
        if ($v >= 10000) { return Format::number((int) round($v / 1000)).' bin'; }
        return Format::number((int) round($v));
    };

    $n = count($labels);
    $k = count($series);

    // Ölçek: en büyük değer (üst üste ise toplam) → 4 aralıklı "temiz" üst sınır
    $max = 0;
    for ($i = 0; $i < $n; $i++) {
        $vals = array_map(fn ($s) => max(0, (float) ($s['values'][$i] ?? 0)), $series);
        $max = max($max, $stacked ? array_sum($vals) : max($vals ?: [0]));
    }
    $rawStep = $max > 0 ? $max / 4 : 1;
    $mag = 10 ** floor(log10($rawStep));
    $step = $mag;
    foreach ([1, 2, 2.5, 5, 10] as $m) { if ($m * $mag >= $rawStep) { $step = $m * $mag; break; } }
    if (! $money && $step < 1) { $step = 1; }
    $top = $step * 4;

    $W = 520; $H = 210; $L = 52; $R = 8; $T = 10; $B = 26;
    $pw = $W - $L - $R; $ph = $H - $T - $B; $base = $T + $ph;
    $band = $n > 0 ? $pw / $n : $pw;
    $y = fn ($v) => $base - ($top > 0 ? $v / $top * $ph : 0);

    $gap = 2;
    $barW = $stacked ? min(24, $band * 0.62) : min(24, max(3, ($band * 0.72 - $gap * ($k - 1)) / max(1, $k)));
    $groupW = $stacked ? $barW : $barW * $k + $gap * ($k - 1);

    // Üstü 4px yuvarlak, tabanı düz sütun yolu
    $col = function (float $x, float $y1, float $y2, float $w, bool $round) {
        $h = $y2 - $y1;
        if ($h <= 0) { return ''; }
        $r = $round ? min(4, $w / 2, $h) : 0;
        $f = fn ($v) => number_format($v, 2, '.', '');
        if ($r <= 0) {
            return "M{$f($x)},{$f($y2)}V{$f($y1)}H{$f($x + $w)}V{$f($y2)}Z";
        }
        return "M{$f($x)},{$f($y2)}V{$f($y1 + $r)}Q{$f($x)},{$f($y1)} {$f($x + $r)},{$f($y1)}H{$f($x + $w - $r)}Q{$f($x + $w)},{$f($y1)} {$f($x + $w)},{$f($y1 + $r)}V{$f($y2)}Z";
    };
    $chartId = 'g'.substr(md5($title), 0, 8);
@endphp
<figure class="chart" aria-labelledby="{{ $chartId }}-t">
    <figcaption>
        <h2 id="{{ $chartId }}-t">{{ $title }}</h2>
        @if ($subtitle)<span class="muted small">{{ $subtitle }}</span>@endif
    </figcaption>

    @if ($k > 1)
        <ul class="chart-legend" aria-label="Açıklama">
            @foreach ($series as $s)
                <li><i style="background: {{ $s['color'] }}"></i>{{ $s['name'] }}</li>
            @endforeach
        </ul>
    @endif

    @if ($max <= 0)
        <p class="muted chart-empty">Bu dönemde veri yok.</p>
    @else
        <div class="chart-box">
            <svg viewBox="0 0 {{ $W }} {{ $H }}" role="img" aria-label="{{ $title }}. Değerler alttaki tabloda.">
                {{-- Yatay kılavuz çizgileri ve değer ekseni --}}
                @for ($t = 0; $t <= 4; $t++)
                    @php $gy = $y($step * $t); @endphp
                    <line x1="{{ $L }}" x2="{{ $W - $R }}" y1="{{ $gy }}" y2="{{ $gy }}" class="{{ $t === 0 ? 'axis' : 'grid' }}"/>
                    <text x="{{ $L - 8 }}" y="{{ $gy + 4 }}" text-anchor="end" class="tick">{{ $axis($step * $t) }}</text>
                @endfor

                @for ($i = 0; $i < $n; $i++)
                    @php
                        $cx = $L + $band * $i + $band / 2;
                        $x0 = $cx - $groupW / 2;
                        $acc = 0;
                        $tip = [$tipLabels[$i]];
                        foreach ($series as $s) { $tip[] = [$s['name'], $fmt($s['values'][$i] ?? 0), $s['color']]; }
                        if ($stacked && $k > 1) { $tip[] = ['Toplam', $fmt(array_sum(array_map(fn ($s) => (float) ($s['values'][$i] ?? 0), $series))), null]; }
                        $lastNonZero = -1;
                        foreach ($series as $si => $s) { if ((float) ($s['values'][$i] ?? 0) > 0) { $lastNonZero = $si; } }
                    @endphp
                    <g class="col">
                        @foreach ($series as $si => $s)
                            @php $v = max(0, (float) ($s['values'][$i] ?? 0)); @endphp
                            @if ($stacked)
                                @php
                                    $y2 = $y($acc); $acc += $v; $y1 = $y($acc);
                                    // üstte başka dilim varsa aradaki 2px boşluk
                                    $isTop = $si === $lastNonZero;
                                    $y1g = $isTop ? $y1 : min($y2, $y1 + $gap);
                                @endphp
                                <path d="{{ $col($x0, $y1g, $y2, $barW, $isTop) }}" fill="{{ $s['color'] }}"/>
                            @else
                                <path d="{{ $col($x0 + $si * ($barW + $gap), $y($v), $base, $barW, true) }}" fill="{{ $s['color'] }}"/>
                            @endif
                        @endforeach
                        <rect class="hit" x="{{ $L + $band * $i }}" y="{{ $T }}" width="{{ $band }}" height="{{ $ph }}" tabindex="0"
                              data-tip="{{ json_encode($tip, JSON_UNESCAPED_UNICODE) }}">
                            <title>{{ $tipLabels[$i] }}: {{ collect($series)->map(fn ($s) => $s['name'].' '.$fmt($s['values'][$i] ?? 0))->implode(', ') }}</title>
                        </rect>
                    </g>
                    @if ($i % $every === 0 || ($i === $n - 1 && ($n - 1) % $every >= max(1, intdiv($every, 2))))
                        <text x="{{ $cx }}" y="{{ $H - 8 }}" text-anchor="middle" class="tick">{{ $labels[$i] }}</text>
                    @endif
                @endfor
            </svg>
        </div>
    @endif

    <details class="chart-table">
        <summary>Tablo olarak göster</summary>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead><tr><th>Dönem</th>@foreach ($series as $s)<th class="n">{{ $s['name'] }}</th>@endforeach</tr></thead>
                <tbody>
                @for ($i = $n - 1; $i >= 0; $i--)
                    <tr><td>{{ $tipLabels[$i] }}</td>@foreach ($series as $s)<td class="n {{ $money ? 'money' : 'num' }}">{{ $fmt($s['values'][$i] ?? 0) }}</td>@endforeach</tr>
                @endfor
                </tbody>
            </table>
        </div>
    </details>
</figure>
