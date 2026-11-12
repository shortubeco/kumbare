@extends('layouts.admin')
@php use App\Support\Labels; @endphp
@section('title', 'Yorum şikâyetleri')

@section('content')
    <div class="p-head">
        <div>
            <h1>Yorum şikâyetleri</h1>
            <p>Şikâyet edilen yorumlar, en eski şikâyet en üstte. Yorumu kaldırırsanız şubenin puanı yeniden hesaplanır.</p>
        </div>
    </div>

    <nav class="tabs" aria-label="Filtre">
        <a href="{{ route('admin.reports') }}" @if ($scope === 'open') aria-current="page" @endif>Açık şikâyetler</a>
        <a href="{{ route('admin.reports', ['durum' => 'resolved']) }}" @if ($scope === 'resolved') aria-current="page" @endif>Sonuçlananlar</a>
    </nav>

    @if ($reviewIds->isEmpty())
        <div class="card"><p class="empty">{{ $scope === 'open' ? 'İncelenecek şikâyet yok.' : 'Kayıt yok.' }}</p></div>
    @endif

    @foreach ($reviewIds as $row)
        @php $review = $reviews[$row->review_id] ?? null; @endphp
        @continue(! $review)
        <article class="card">
            <div class="card-head">
                <div>
                    <h2>{{ $review->branch->business->name }} – {{ $review->branch->name }}</h2>
                    <span class="muted small">
                        {{ $review->customer?->fullName() }} (#{{ $review->customer?->customer_no }}) · {{ Labels::dateTime($review->created_at) }}
                    </span>
                </div>
                <div>
                    <span class="score">{{ $review->score }} / 10</span>
                    {!! Labels::badge('review', $review->status) !!}
                </div>
            </div>

            <div class="quote">{{ $review->comment ?: '(Yorum metni yok, yalnızca puan)' }}</div>
            @if ($review->reply)
                <p class="small"><strong>Restoranın cevabı:</strong> {{ $review->reply->body }}</p>
            @endif
            @if ($review->status === 'removed')
                <p class="small muted">Kaldırılma gerekçesi: {{ $review->removed_reason }} ({{ Labels::dateTime($review->removed_at) }})</p>
            @endif

            <h3 style="margin-top:10px">Şikâyetler ({{ $row->cnt }})</h3>
            <ul class="small">
                @foreach ($reports[$review->id] ?? [] as $r)
                    <li>{{ Labels::dateTime($r->created_at) }} · {{ $r->reporter_type === 'customer' ? 'Müşteri' : 'Panel kullanıcısı' }}: {{ $r->reason }}</li>
                @endforeach
            </ul>

            @if ($scope === 'open')
                <div class="actions">
                    @if ($review->status !== 'removed')
                        @include('admin.partials.reason-action', [
                            'action' => route('admin.reviews.remove', $review), 'label' => 'Yorumu kaldır', 'btn' => 'danger', 'align' => 'left',
                            'title' => 'Kaldırma gerekçesi', 'hint' => 'Yorum sitede ve uygulamada görünmez; şikâyetler kapanır.',
                        ])
                    @endif
                    <form method="post" action="{{ route('admin.reviews.dismiss', $review) }}">
                        @csrf
                        <button class="btn sm" type="submit">Şikâyet yersiz, yorum kalsın</button>
                    </form>
                </div>
            @endif
        </article>
    @endforeach

    @include('site.partials.pagination', ['paginator' => $reviewIds])
@endsection
