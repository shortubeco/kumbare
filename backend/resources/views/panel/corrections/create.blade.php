@extends('layouts.panel')
@php
    use App\Support\Format; use App\Support\Labels;
    $isEarn = $tx->type === 'earn';
    $abs = abs((float) $tx->amount);
    $dir = old('direction', $isEarn ? 'subtract' : 'add');
@endphp
@section('title', 'Düzeltme isteği')

@section('content')
    <div class="p-head">
        <div>
            <div class="crumb"><a href="{{ route('panel.customers.show', $tx->customer->customer_no) }}">← {{ $tx->customer->fullName() }}</a></div>
            <h1>Düzeltme isteği</h1>
            <p>İşlemler silinmez; hata, müşterinin onayladığı ters bir hareketle düzeltilir. <b>Müşteri onaylamadan bakiye değişmez</b>; müşteri reddederse düzeltme yapılamaz.</p>
        </div>
    </div>

    <div class="grid two" style="align-items:start">
        <div class="card">
            <h2>Düzeltilecek işlem</h2>
            <dl class="kv">
                <dt>Müşteri</dt><dd>{{ $tx->customer->fullName() }} <span class="muted">#{{ $tx->customer->customer_no }}</span></dd>
                <dt>İşlem</dt><dd>{!! Labels::badge('tx', $tx->type) !!} #{{ $tx->id }} · {{ Labels::dateTime($tx->created_at) }}</dd>
                @if ($isEarn)
                    <dt>Harcama</dt><dd class="money">{{ Format::money($tx->spend_amount) }} × {{ Format::rate($tx->reward_rate) }}@if ($tx->campaign) ({{ $tx->campaign->title }})@endif</dd>
                @endif
                <dt>Tutar</dt><dd class="money">{{ $tx->amount > 0 ? '+' : '' }}{{ Format::money($tx->amount) }}</dd>
                <dt>Şu anki bakiye</dt><dd class="money">{{ Format::money($tx->wallet->balance) }}</dd>
            </dl>
            @if ($previous->isNotEmpty())
                <h3 style="margin-top:14px">Bu işlem için önceki düzeltmeler</h3>
                <ul class="small">
                    @foreach ($previous as $p)
                        <li>{{ Labels::dateTime($p->created_at) }}: {{ $p->amount > 0 ? '+' : '' }}{{ Format::money($p->amount) }} — {{ Labels::text('approval', $p->status) }}@if ($p->failure_reason) ({{ $p->failure_reason }})@endif</li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="card">
            @if ($pending)
                <h2>Onay bekleyen düzeltme var</h2>
                <p>Bu işlem için {{ Labels::dateTime($pending->created_at) }} tarihinde gönderilen
                   <b class="money">{{ $pending->amount > 0 ? '+' : '' }}{{ Format::money($pending->amount) }}</b> düzeltme müşteri onayı bekliyor.
                   Yeni istek göndermek için önce onu geri çekin.</p>
                <form method="post" action="{{ route('panel.corrections.withdraw', $pending->id) }}">
                    @csrf
                    <button class="btn danger" type="submit">Bekleyen isteği geri çek</button>
                </form>
            @else
                <h2>Düzeltme</h2>
                <form method="post" action="{{ route('panel.corrections.store') }}" novalidate>
                    @csrf
                    <input type="hidden" name="transaction_id" value="{{ $tx->id }}">
                    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $key) }}">

                    <fieldset>
                        <legend>Ne yapılsın?</legend>
                        <div class="choice">
                            <label><input type="radio" name="direction" value="subtract" @checked($dir === 'subtract')>
                                <span><b>Bakiyeden düş</b><small>{{ $isEarn ? 'Fazla kazandırılan ödülü geri al' : 'Eksik düşülen kullanımı tamamla' }}</small></span></label>
                            <label><input type="radio" name="direction" value="add" @checked($dir === 'add')>
                                <span><b>Bakiyeye ekle</b><small>{{ $isEarn ? 'Eksik kazandırılan ödülü tamamla' : 'Fazla düşülen kullanımı iade et' }}</small></span></label>
                        </div>
                    </fieldset>

                    <div class="field">
                        <label for="amount">Düzeltme tutarı (TL)</label>
                        <input id="amount" name="amount" type="text" inputmode="decimal" value="{{ old('amount') }}" placeholder="Örn. 25,00" required @error('amount') aria-invalid="true" @enderror>
                        <span class="hint">
                            @if ($isEarn)
                                Geri alınabilecek en fazla tutar: {{ Format::money($abs) }} (bu işlemde kazanılan).
                            @else
                                İade edilebilecek en fazla tutar: {{ Format::money($abs) }} (bu işlemde kullanılan).
                            @endif
                        </span>
                        @error('amount')<span class="field-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="field">
                        <label for="reason">Gerekçe</label>
                        <textarea id="reason" name="reason" maxlength="1000" required placeholder="Örn. Hesap tutarı yanlış girildi: 1.200 yerine 2.100 TL yazılmış." @error('reason') aria-invalid="true" @enderror>{{ old('reason') }}</textarea>
                        <span class="hint">Müşteri bu gerekçeyi uygulamada görür.</span>
                        @error('reason')<span class="field-error">{{ $message }}</span>@enderror
                    </div>

                    <button class="btn gold" type="submit">Müşteriye onaya gönder</button>
                </form>
            @endif
        </div>
    </div>
@endsection
