@extends('layouts.admin')
@section('title', 'Paketler ve ayarlar')

@php
    $v = fn (string $key) => old($key, $settings[$key] ?? '');
    $money = fn ($n) => $n === null || $n === '' ? '' : number_format((float) $n, 2, ',', '.');
    $free = $plans['free'] ?? null;
    $pro = $plans['pro'] ?? null;
@endphp

@section('content')
    <div class="p-head">
        <div>
            <h1>Paketler ve ayarlar</h1>
            <p>Değişiklikler kaydedildiği anda tüm şubeler için geçerli olur ve denetim kaydına yazılır.</p>
        </div>
    </div>

    <form method="post" action="{{ route('admin.settings.update') }}" novalidate>
        @csrf

        <div class="grid two">
            <div class="card">
                <fieldset>
                    <legend>Ücretsiz paket</legend>
                    <div class="field">
                        <label for="free_monthly_customer_limit">Aylık farklı müşteri limiti (şube başına)</label>
                        <input id="free_monthly_customer_limit" type="number" name="free_monthly_customer_limit" min="1" required value="{{ $v('free_monthly_customer_limit') }}">
                        <span class="hint">Ay ortasında değişirse hemen geçerli olur; o ay sayılmış müşteriler etkilenmez.</span>
                    </div>
                    <div class="form-grid">
                        <div class="field">
                            <label for="warning_threshold_1">1. uyarı (%)</label>
                            <input id="warning_threshold_1" type="number" name="warning_threshold_1" min="1" max="99" required value="{{ $v('warning_threshold_1') }}">
                        </div>
                        <div class="field">
                            <label for="warning_threshold_2">2. uyarı (%)</label>
                            <input id="warning_threshold_2" type="number" name="warning_threshold_2" min="1" max="99" required value="{{ $v('warning_threshold_2') }}">
                        </div>
                    </div>
                    <div class="field">
                        <label for="free_description">Paket açıklaması</label>
                        <textarea id="free_description" name="free_description" maxlength="1000">{{ old('free_description', $free?->description) }}</textarea>
                    </div>
                </fieldset>
            </div>

            <div class="card">
                <fieldset>
                    <legend>Profesyonel paket</legend>
                    <div class="form-grid">
                        <div class="field">
                            <label for="pro_monthly_price">Aylık fiyat (TL)</label>
                            <input id="pro_monthly_price" type="text" inputmode="decimal" name="pro_monthly_price" value="{{ old('pro_monthly_price', $money($pro?->monthly_price)) }}" placeholder="Örn. 499,00">
                        </div>
                        <div class="field">
                            <label for="pro_yearly_price">Yıllık fiyat (TL)</label>
                            <input id="pro_yearly_price" type="text" inputmode="decimal" name="pro_yearly_price" value="{{ old('pro_yearly_price', $money($pro?->yearly_price)) }}" placeholder="Örn. 4.990,00">
                        </div>
                    </div>
                    <div class="field">
                        <label for="subscription_expiry_warning_days">Bitişten kaç gün önce uyarılsın</label>
                        <input id="subscription_expiry_warning_days" type="number" name="subscription_expiry_warning_days" min="1" max="60" required value="{{ $v('subscription_expiry_warning_days') }}">
                    </div>
                    <div class="field">
                        <label for="pro_description">Paket açıklaması</label>
                        <textarea id="pro_description" name="pro_description" maxlength="1000">{{ old('pro_description', $pro?->description) }}</textarea>
                    </div>
                </fieldset>
            </div>
        </div>

        <div class="grid two">
            <div class="card">
                <fieldset>
                    <legend>Havale bilgileri</legend>
                    <p class="muted small">Şube yöneticileri ödeme ekranında bu bilgileri görür.</p>
                    <div class="field">
                        <label for="bank_name">Banka</label>
                        <input id="bank_name" type="text" name="bank_name" maxlength="100" value="{{ $v('bank_name') }}">
                    </div>
                    <div class="field">
                        <label for="iban">IBAN</label>
                        <input id="iban" type="text" name="iban" maxlength="40" value="{{ $v('iban') }}" placeholder="TR00 0000 0000 0000 0000 0000 00" autocomplete="off">
                    </div>
                    <div class="field">
                        <label for="account_holder">Alıcı adı</label>
                        <input id="account_holder" type="text" name="account_holder" maxlength="150" value="{{ $v('account_holder') }}">
                    </div>
                </fieldset>
            </div>

            <div class="card">
                <fieldset>
                    <legend>Sponsorluk</legend>
                    <div class="field">
                        <label for="sponsorship_monthly_price">Aylık sponsorluk fiyatı (TL, şube başına)</label>
                        <input id="sponsorship_monthly_price" type="text" inputmode="decimal" name="sponsorship_monthly_price" value="{{ old('sponsorship_monthly_price', $money($settings['sponsorship_monthly_price'] ?? null)) }}" placeholder="Örn. 750,00">
                    </div>
                </fieldset>
            </div>
        </div>

        <button class="btn primary" type="submit">Ayarları kaydet</button>
    </form>
@endsection
