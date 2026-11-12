@extends('layouts.panel')
@php use App\Support\Labels; @endphp
@section('title', 'Bildirimler')

@section('content')
    <div class="p-head">
        <div>
            <h1>Bildirimler</h1>
            <p>Müşteri limiti uyarıları, havale ve paket sonuçları, müşteri onayları. Yeni bildirimler sarı arka planla gösterilir ve bu sayfayı açtığınızda okunmuş sayılır.</p>
        </div>
    </div>

    <div class="card flush">
        @forelse ($items as $it)
            <div @class(['notif', 'unread' => $it['unread']])>
                <span class="dot {{ $it['kind'] }}" aria-hidden="true"></span>
                <div style="flex:1">
                    <b>{{ $it['title'] }}</b>@if ($it['unread']) <span class="badge warn">Yeni</span>@endif
                    @if ($it['text'])<div class="small">{{ $it['text'] }}</div>@endif
                    <div class="muted small">{{ Labels::dateTime($it['n']->created_at) }}@if ($it['url']) · <a href="{{ $it['url'] }}">Ayrıntı →</a>@endif</div>
                </div>
            </div>
        @empty
            <p class="empty">Bildirim yok.</p>
        @endforelse
        @include('site.partials.pagination', ['paginator' => $items])
    </div>
@endsection
