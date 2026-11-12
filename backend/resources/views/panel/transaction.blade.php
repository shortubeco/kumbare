@extends('layouts.panel')
@php use App\Support\Format; @endphp
@section('title', 'İşlem ekranı')

@section('content')
    <div class="p-head">
        <div>
            <h1>İşlem ekranı</h1>
            <p>Müşteri numarasını girin; ödül kazandırın veya müşterinin onayıyla ödül kullandırın.</p>
        </div>
        <div class="tx-meta">
            <span class="muted small">Şube ödül oranı</span>
            <b>{{ Format::rate($branch->reward_rate) }}</b>
            @if ($counter['plan'] === 'pro')
                <span class="badge pro">Profesyonel</span>
            @else
                <span class="badge {{ $counter['limit_reached'] ? 'bad' : '' }}" title="Bu ay ödül kazandırılan farklı müşteri">
                    Bu ay {{ Format::number($counter['unique_customers']) }} / {{ Format::number($counter['limit']) }} müşteri
                </span>
            @endif
        </div>
    </div>

    <noscript><div class="flash err">İşlem ekranı için tarayıcınızda JavaScript açık olmalıdır.</div></noscript>

    @unless ($can['lookup'])
        <div class="flash err">Hesabınıza müşteri sorgulama, ödül kazandırma veya kullandırma yetkisi verilmemiş. Şube yöneticinizle görüşün.</div>
    @else
    <div id="tx" class="tx"
         data-lookup="{{ route('panel.tx.lookup', ['customerNo' => '__NO__']) }}"
         data-campaigns="{{ route('panel.tx.campaigns', ['customerNo' => '__NO__']) }}"
         data-preview="{{ route('panel.tx.earn.preview') }}"
         data-earn="{{ route('panel.tx.earn') }}"
         data-redeem="{{ route('panel.tx.redeem') }}"
         data-request="{{ route('panel.tx.request', ['approvalRequest' => '__ID__']) }}"
         data-withdraw="{{ route('panel.tx.withdraw', ['approvalRequest' => '__ID__']) }}"
         data-can-earn="{{ $can['earn'] ? 1 : 0 }}"
         data-can-redeem="{{ $can['redeem'] ? 1 : 0 }}"
         data-upgrade="{{ $isManager ? route('panel.package') : '' }}">

        {{-- 1. Müşteri bul --}}
        <section class="card tx-step">
            <form id="tx-find" class="tx-find" autocomplete="off" data-no-guard>
                <label for="tx-no">Müşteri numarası</label>
                <div class="tx-find-row">
                    <input id="tx-no" name="no" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="12" placeholder="Örn. 125" autofocus>
                    <button class="btn primary big" type="submit">Bul</button>
                </div>
            </form>
            <div id="tx-error" class="flash err" role="alert" hidden></div>

            <div id="tx-customer" class="tx-customer" hidden>
                <div>
                    <div class="tx-name"><span id="tx-name"></span> <span class="muted num" id="tx-cno"></span></div>
                    <div class="tx-tags">
                        <span id="tx-bday" class="badge warn" hidden>🎂 Bugün doğum günü</span>
                        <span id="tx-pending" class="badge info" hidden></span>
                    </div>
                </div>
                <div class="tx-balance">
                    <span class="muted small">Bu şubedeki bakiye</span>
                    <b id="tx-balance" class="money"></b>
                </div>
                <div class="tx-actions">
                    @if ($can['earn'])<button type="button" class="btn gold big" id="tx-go-earn">Ödül kazandır</button>@endif
                    @if ($can['redeem'])<button type="button" class="btn primary big" id="tx-go-redeem">Ödül kullandır</button>@endif
                    <button type="button" class="btn big" id="tx-reset">Yeni müşteri</button>
                </div>
            </div>
        </section>

        {{-- 2a. Ödül kazandır --}}
        <section class="card tx-step" id="tx-earn" hidden>
            <h2>Ödül kazandır</h2>
            <div class="field">
                <label for="tx-spend">Ödenen tutar (TL)</label>
                <input id="tx-spend" class="big-input" type="text" inputmode="decimal" placeholder="Örn. 1.000,00">
            </div>
            <fieldset>
                <legend>Kampanya</legend>
                <div id="tx-campaigns" class="tx-campaigns"></div>
            </fieldset>
            <div id="tx-earn-summary" class="tx-summary" hidden></div>
            <div id="tx-limit" class="flash err" hidden></div>
            <div class="actions">
                <button type="button" class="btn big" data-cancel>İptal</button>
                <button type="button" class="btn gold big" id="tx-earn-confirm" disabled>Onayla</button>
            </div>
        </section>

        {{-- 2b. Ödül kullandır --}}
        <section class="card tx-step" id="tx-redeem" hidden>
            <h2>Ödül kullandır</h2>
            <div id="tx-redeem-form">
                <div class="field">
                    <label for="tx-amount">Kullanılacak tutar (TL)</label>
                    <input id="tx-amount" class="big-input" type="text" inputmode="decimal" placeholder="Örn. 250,00">
                    <span class="hint" id="tx-amount-hint"></span>
                </div>
                <p class="muted small">İstek müşterinin Kumbare uygulamasına gider. <b>Müşteri onaylamadan indirim uygulamayın.</b></p>
                <div class="actions">
                    <button type="button" class="btn big" data-cancel>İptal</button>
                    <button type="button" class="btn primary big" id="tx-redeem-send">İsteği gönder</button>
                </div>
            </div>
            <div id="tx-wait" class="tx-wait" hidden>
                <div class="tx-spinner" aria-hidden="true"></div>
                <div>
                    <b>Müşteri onayı bekleniyor…</b>
                    <p class="muted small">Müşteri uygulamayı açıp "Bildirimler"den isteği onaylamalı. Bu ekran kendini yeniler.</p>
                    <div class="actions">
                        <button type="button" class="btn" id="tx-refresh">Şimdi kontrol et</button>
                        <button type="button" class="btn danger" id="tx-withdraw">İsteği geri çek</button>
                    </div>
                </div>
            </div>
        </section>

        {{-- 3. Sonuç --}}
        <section class="tx-result" id="tx-result" hidden>
            <div id="tx-result-box"></div>
            <div class="actions" style="justify-content:center">
                <button type="button" class="btn primary big" id="tx-next">Yeni müşteri</button>
                <button type="button" class="btn big" id="tx-same">Aynı müşteriyle devam</button>
            </div>
        </section>
    </div>
    @endunless
@endsection

@push('scripts')
    <script src="/js/panel-islem.js?v={{ @filemtime(public_path('js/panel-islem.js')) }}" defer></script>
@endpush
