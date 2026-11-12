@if ($paginator->hasPages())
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $pages = collect([1, $current - 1, $current, $current + 1, $last])->filter(fn ($p) => $p >= 1 && $p <= $last)->unique()->sort()->values();
    @endphp
    <nav class="sayfalama" aria-label="Sayfalar">
        @if ($paginator->onFirstPage())
            <span class="disabled" aria-hidden="true">Önceki</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev">Önceki</a>
        @endif

        @foreach ($pages as $i => $p)
            @if ($i > 0 && $p - $pages[$i - 1] > 1)
                <span class="disabled" aria-hidden="true">…</span>
            @endif
            @if ($p === $current)
                <span aria-current="page">{{ $p }}</span>
            @else
                <a href="{{ $paginator->url($p) }}" aria-label="Sayfa {{ $p }}">{{ $p }}</a>
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next">Sonraki</a>
        @else
            <span class="disabled" aria-hidden="true">Sonraki</span>
        @endif
    </nav>
@endif
