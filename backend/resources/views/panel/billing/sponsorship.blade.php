@extends('layouts.panel')
@php use App\Support\Format; use App\Support\Labels; @endphp
@section('title', 'Sponsorluk')

@section('content')
    <div class="p-head">
        <div>
            <h1>Sponsorluk</h1>
            <p>Sponsorlu şubeler web sitesinde ve uygulamada listelerin üstünde, "Sponsorlu" etiketiyle gösterilir.
               Sponsorluk 1 aydır: ayın 1'inde başlar, sonraki ayın 1'inde biter.</p>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2>Ay seçin</h2>
            <span class="muted">
                @if ($price)Aylık ücret: <b class="money">{{ Format::money($price) }}</b>@else Ücret yakında açıklanacak @endif
            </span>
        </div>
        <div class="months">
            @foreach ($months as $m => $state)
                <div @class(['month', 'sponsored' => $state === 'sponsored', 'pending' => $state === 'pending'])>
                    <b>{{ Labels::month($m) }}</b>
                    @if ($state === 'sponsored')
                        <span class="badge ok">Sponsorlu</span>
                    @elseif ($state === 'pending')
                        <span class="badge warn">Bildirim onay bekliyor</span>
                    @else
                        <span class="muted small">{{ $loop->first ? 'Hemen başlar (bu ay)' : 'Ayın 1\'inde başlar' }}</span>
                        <a class="btn sm primary" href="{{ route('panel.payment.create', ['amac' => 'sponsorluk', 'ay' => $m]) }}">Bu ay için havale bildir</a>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <div class="grid two">
        @include('panel.billing._bank')
        <div class="card">
            <h2>Nasıl çalışır?</h2>
            <ol class="steps">
                <li>Ay seçip ücreti yandaki hesaba havale/EFT ile gönderin.</li>
                <li>Havale bildirimi formunu doldurun.</li>
                <li>Kumbare ekibi onayladığında şubeniz o ay boyunca sponsorlu görünür.</li>
            </ol>
            @if ($past->isNotEmpty())
                <h3 style="margin-top:12px">Geçmiş sponsorluklar</h3>
                <ul class="small">
                    @foreach ($past as $s)
                        <li>{{ Labels::month($s->month) }} — {{ Labels::text('sponsorship', $s->status) }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    @include('panel.billing._history', ['history' => $history])
@endsection
