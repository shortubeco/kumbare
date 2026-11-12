{{-- İş planındaki biçim: "8.7 / 10 — 245 değerlendirme". Yıldız kullanılmaz. --}}
@if ((int) $count > 0)
    <span class="score"><b>{{ \App\Support\Format::score($avg) }}</b><small>/ 10 — {{ \App\Support\Format::number((int) $count) }} değerlendirme</small></span>
@else
    <span class="score-none">Henüz değerlendirme yok</span>
@endif
