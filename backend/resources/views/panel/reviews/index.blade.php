@extends('layouts.panel')
@php use App\Support\Format; use App\Support\Labels; $total = array_sum($distribution); $maxDist = max(1, max($distribution)); @endphp
@section('title', 'Değerlendirmeler')

@section('content')
    <div class="p-head">
        <div>
            <h1>Değerlendirmeler</h1>
            <p>Yalnızca şubenizde ödül kazanmış müşteriler 1–10 puan verebilir. Yorumlar silinemez veya düzenlenemez; her yoruma bir cevap yazabilirsiniz. Uygunsuz yorumu Kumbare'ye şikâyet edebilirsiniz.</p>
        </div>
    </div>

    <div class="grid two" style="margin-bottom:18px">
        <div class="card" style="margin:0">
            <h2>Puan</h2>
            @if ($branch->rating_count > 0)
                <p><span class="score" style="font-size:2.2rem">{{ Format::score($branch->rating_avg) }}</span> <span class="muted">/ 10 — {{ Format::number($branch->rating_count) }} değerlendirme</span></p>
                @if ($branch->rating_count < 10)<p class="muted small">"En yüksek puan" listelerinde görünmek için en az 10 değerlendirme gerekir.</p>@endif
            @else
                <p class="muted">Henüz değerlendirme yok.</p>
            @endif
            <p class="small">Cevap bekleyen yorum: <b>{{ Format::number($unanswered) }}</b></p>
        </div>
        <div class="card" style="margin:0">
            <h2>Puan dağılımı</h2>
            <div class="dist">
                @foreach ($distribution as $score => $cnt)
                    <div class="dist-row">
                        <span class="num"><b>{{ $score }}</b></span>
                        <span class="bar-track"><span class="a" style="width: {{ $cnt * 100 / $maxDist }}%; background:#00897b"></span></span>
                        <span class="num small" style="text-align:right">{{ Format::number($cnt) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <nav class="tabs" aria-label="Filtre">
        @foreach (['tumu' => 'Tümü', 'cevapsiz' => 'Cevap bekleyen', 'dusuk' => 'Düşük puanlı (1–5)'] as $k => $label)
            <a href="{{ route('panel.reviews', ['filtre' => $k, 'siralama' => $sort]) }}" @if ($filter === $k) aria-current="page" @endif>{{ $label }}@if ($k === 'cevapsiz')<span class="c">{{ $unanswered }}</span>@endif</a>
        @endforeach
        <a href="{{ route('panel.reviews', ['filtre' => $filter, 'siralama' => $sort === 'puan' ? 'yeni' : 'puan']) }}">Sırala: {{ $sort === 'puan' ? 'en düşük puan önce' : 'en yeni önce' }} ⇅</a>
    </nav>

    <div class="card flush">
        @forelse ($reviews as $r)
            <article class="review" id="yorum-{{ $r->id }}">
                <div class="review-head">
                    <div><b>{{ $r->customer?->publicName() }}</b> <span class="muted small">· {{ Labels::date($r->created_at) }}@if ($r->updated_at && $r->updated_at->gt($r->created_at->copy()->addMinute())) (güncellendi)@endif</span></div>
                    <span class="score">{{ $r->score }} / 10</span>
                </div>
                @if ($r->comment)<div class="quote">{{ $r->comment }}</div>@else<p class="muted small">Yalnızca puan verildi.</p>@endif

                @if ($r->reply)
                    <div class="reply"><b class="small">Cevabınız</b> <span class="muted small">· {{ Labels::date($r->reply->updated_at) }}</span><br>{{ $r->reply->body }}</div>
                @endif

                <div class="actions" style="margin-top:10px">
                    <details class="act left">
                        <summary class="btn sm {{ $r->reply ? '' : 'primary' }}">{{ $r->reply ? 'Cevabı düzenle' : 'Cevap yaz' }}</summary>
                        <div class="pop" style="width:min(520px, 86vw)">
                            <form method="post" action="{{ route('panel.reviews.reply', $r->id) }}">
                                @csrf
                                <div class="field">
                                    <label for="cevap-{{ $r->id }}">Cevap</label>
                                    <textarea id="cevap-{{ $r->id }}" name="body" maxlength="1000" required>{{ $r->reply?->body }}</textarea>
                                    <span class="hint">Herkese açık görünür. Kibar ve kısa tutun.</span>
                                </div>
                                <button class="btn sm primary" type="submit">Yayımla</button>
                            </form>
                        </div>
                    </details>
                    @if (in_array($r->id, $reported, true))
                        <span class="badge warn">Şikâyetiniz inceleniyor</span>
                    @else
                        @include('admin.partials.reason-action', [
                            'action' => route('panel.reviews.report', $r->id), 'label' => 'Şikâyet et', 'btn' => 'danger', 'align' => 'left',
                            'title' => 'Şikâyet gerekçesi', 'hint' => 'Örn. hakaret, kişisel bilgi, gerçeği yansıtmayan iddia. Kumbare ekibi inceler.',
                        ])
                    @endif
                </div>
            </article>
        @empty
            <p class="empty">{{ $filter === 'tumu' ? 'Henüz değerlendirme yok.' : 'Bu filtrede değerlendirme yok.' }}</p>
        @endforelse
        @include('site.partials.pagination', ['paginator' => $reviews])
    </div>
@endsection
