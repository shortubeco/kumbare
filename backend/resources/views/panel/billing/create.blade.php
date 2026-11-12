@extends('layouts.panel')
@php
    use App\Support\Format; use App\Support\Labels;
    $p = old('purpose', $purpose);
    $c = old('billing_cycle', $cycle);
    $selMonth = old('sponsorship_month', $month);
    $priceAttr = fn ($v) => $v ? Format::money($v) : '';
    $available = collect($months)->filter(fn ($s) => $s === 'available');
@endphp
@section('title', 'Havale bildirimi')

@section('content')
    <div class="p-head">
        <div>
            <div class="crumb"><a href="{{ $p === 'sponsorship' ? route('panel.sponsorship') : route('panel.package') }}">← {{ $p === 'sponsorship' ? 'Sponsorluk' : 'Paket ve ödeme' }}</a></div>
            <h1>Havale bildirimi</h1>
            <p>Havaleyi yaptıktan sonra bu formu doldurun. Kumbare ekibi kontrol edip onayladığında işleminiz başlar.</p>
        </div>
    </div>

    <div class="grid two" style="align-items:start">
        <form class="card" id="havale-formu" method="post" action="{{ route('panel.payment.store') }}" enctype="multipart/form-data" novalidate
              data-price-monthly="{{ $priceAttr($prices['monthly']) }}"
              data-price-yearly="{{ $priceAttr($prices['yearly']) }}"
              data-price-sponsorship="{{ $priceAttr($prices['sponsorship']) }}">
            @csrf

            <fieldset>
                <legend>Ne için ödeme yaptınız?</legend>
                <div class="choice">
                    <label><input type="radio" name="purpose" value="subscription" @checked($p === 'subscription')>
                        <span><b>Profesyonel paket</b><small>Sınırsız aylık müşteri</small></span></label>
                    <label><input type="radio" name="purpose" value="sponsorship" @checked($p === 'sponsorship') @disabled($available->isEmpty())>
                        <span><b>Sponsorluk</b><small>Listelerde üstte görünme</small></span></label>
                </div>
            </fieldset>

            <fieldset data-show="subscription" @if ($p !== 'subscription') hidden @endif>
                <legend>Paket dönemi</legend>
                <div class="choice">
                    <label><input type="radio" name="billing_cycle" value="monthly" @checked($c === 'monthly')>
                        <span><b>Aylık</b><small>{{ $prices['monthly'] ? Format::money($prices['monthly']) : 'Fiyat belirlenmedi' }}</small></span></label>
                    <label><input type="radio" name="billing_cycle" value="yearly" @checked($c === 'yearly')>
                        <span><b>Yıllık</b><small>{{ $prices['yearly'] ? Format::money($prices['yearly']) : 'Fiyat belirlenmedi' }}</small></span></label>
                </div>
                @error('billing_cycle')<span class="field-error">{{ $message }}</span>@enderror
            </fieldset>

            <div class="field" data-show="sponsorship" @if ($p !== 'sponsorship') hidden @endif>
                <label for="sponsorship_month">Sponsorluk ayı</label>
                <select id="sponsorship_month" name="sponsorship_month" @error('sponsorship_month') aria-invalid="true" @enderror>
                    @foreach ($available as $m => $s)
                        <option value="{{ $m }}" @selected($selMonth === $m)>{{ Labels::month($m) }}{{ $loop->first && $m === now()->startOfMonth()->toDateString() ? ' (bu ay)' : '' }}</option>
                    @endforeach
                </select>
                @error('sponsorship_month')<span class="field-error">{{ $message }}</span>@enderror
            </div>

            <div class="form-grid">
                <div class="field">
                    <label for="amount">Gönderdiğiniz tutar (TL)</label>
                    <input id="amount" name="amount" type="text" inputmode="decimal" value="{{ old('amount') }}" placeholder="Örn. 500,00" required @error('amount') aria-invalid="true" @enderror>
                    <span class="hint" id="beklenen"></span>
                    @error('amount')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="transfer_date">Havale tarihi</label>
                    <input id="transfer_date" name="transfer_date" type="date" value="{{ old('transfer_date', now()->toDateString()) }}"
                           max="{{ now()->toDateString() }}" min="{{ now()->subDays(60)->toDateString() }}" required @error('transfer_date') aria-invalid="true" @enderror>
                    @error('transfer_date')<span class="field-error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="field">
                <label for="sender_name">Gönderen (hesap sahibi)</label>
                <input id="sender_name" name="sender_name" type="text" maxlength="150" value="{{ old('sender_name', $sender) }}" required @error('sender_name') aria-invalid="true" @enderror>
                <span class="hint">Havalenin hangi isimden yapıldığını yazın; kontrolü hızlandırır.</span>
                @error('sender_name')<span class="field-error">{{ $message }}</span>@enderror
            </div>

            <div class="field">
                <label for="receipt">Dekont <span class="muted">(isteğe bağlı)</span></label>
                <input id="receipt" name="receipt" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*" @error('receipt') aria-invalid="true" @enderror>
                <span class="hint">PDF veya fotoğraf, en fazla 4 MB.</span>
                @error('receipt')<span class="field-error">{{ $message }}</span>@enderror
            </div>

            <div class="field">
                <label for="description">Not <span class="muted">(isteğe bağlı)</span></label>
                <textarea id="description" name="description" maxlength="1000">{{ old('description') }}</textarea>
            </div>

            <button class="btn gold" type="submit">Bildirimi gönder</button>
        </form>

        <div>
            @include('panel.billing._bank')
            <div class="card">
                <h2>Bilmeniz gerekenler</h2>
                <ul class="small">
                    <li>Bildirim gönderdikten sonra onaylanana kadar aynı paket için yeni bildirim gönderemezsiniz.</li>
                    <li>Profesyonel paket onay anında başlar; paketiniz devam ediyorsa yeni dönem bitiş tarihinden başlar.</li>
                    <li>Sponsorluk seçtiğiniz ayın 1'inde başlar (bu ay seçildiyse onaylanınca hemen).</li>
                    <li>Tutar eksik veya havale bulunamazsa bildirim gerekçesiyle reddedilir; sonucu "Havale bildirimleriniz" listesinde görürsünüz.</li>
                </ul>
            </div>
        </div>
    </div>
@endsection
