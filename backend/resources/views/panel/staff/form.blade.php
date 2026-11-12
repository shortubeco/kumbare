@extends('layouts.panel')
@php
    $m = $member;
    $selected = old('permissions', $m ? $m->permissions->pluck('permission')->all() : [\App\Models\PanelUser::PERM_LOOKUP, \App\Models\PanelUser::PERM_EARN]);
@endphp
@section('title', $m ? 'Çalışanı düzenle' : 'Çalışan ekle')

@section('content')
    <div class="p-head">
        <div>
            <div class="crumb"><a href="{{ route('panel.staff') }}">← Çalışanlar</a></div>
            <h1>{{ $m ? $m->fullName() : 'Çalışan ekle' }}</h1>
            <p>{{ $m ? 'Bilgileri, yetkileri veya şifreyi güncelleyin.' : 'Çalışan, burada belirlediğiniz e-posta ve şifreyle panele giriş yapar.' }}</p>
        </div>
    </div>

    <form method="post" action="{{ $m ? route('panel.staff.update', $m->id) : route('panel.staff.store') }}" novalidate>
        @csrf
        <div class="grid two" style="align-items:start">
            <div class="card">
                <h2>Bilgiler</h2>
                <div class="form-grid">
                    <div class="field">
                        <label for="first_name">Ad</label>
                        <input id="first_name" name="first_name" type="text" maxlength="100" value="{{ old('first_name', $m?->first_name) }}" required @error('first_name') aria-invalid="true" @enderror>
                        @error('first_name')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="last_name">Soyad</label>
                        <input id="last_name" name="last_name" type="text" maxlength="100" value="{{ old('last_name', $m?->last_name) }}" required @error('last_name') aria-invalid="true" @enderror>
                        @error('last_name')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="field">
                    <label for="email">E-posta (giriş adı)</label>
                    <input id="email" name="email" type="email" maxlength="255" value="{{ old('email', $m?->email) }}" required autocomplete="off" @error('email') aria-invalid="true" @enderror>
                    @error('email')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="phone">Telefon <span class="muted">(isteğe bağlı)</span></label>
                    <input id="phone" name="phone" type="tel" value="{{ old('phone', $m?->phone) }}" @error('phone') aria-invalid="true" @enderror>
                    @error('phone')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <fieldset>
                    <legend>{{ $m ? 'Şifreyi değiştir (isteğe bağlı)' : 'Şifre' }}</legend>
                    <div class="form-grid">
                        <div class="field">
                            <label for="password">{{ $m ? 'Yeni şifre' : 'Şifre' }}</label>
                            <input id="password" name="password" type="password" autocomplete="new-password" @unless ($m) required @endunless @error('password') aria-invalid="true" @enderror>
                            @error('password')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="password_confirmation">Şifre (tekrar)</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password">
                        </div>
                    </div>
                    <span class="hint muted small">En az 10 karakter. Şifreyi çalışana kendiniz iletin; çalışan daha sonra "Şifremi unuttum" ile değiştirebilir.</span>
                </fieldset>
            </div>

            <div class="card">
                <h2>Yetkiler</h2>
                <div class="perm-list">
                    @foreach ($permissions as $key => [$label, $hint])
                        <label><input type="checkbox" name="permissions[]" value="{{ $key }}" @checked(in_array($key, $selected, true))>
                            <span><b>{{ $label }}</b><small>{{ $hint }}</small></span></label>
                    @endforeach
                </div>
                <p class="muted small" style="margin-top:10px">Düzeltme isteği, çalışan yönetimi, oran ve kampanya ayarları yalnızca şube yöneticisindedir.</p>
            </div>
        </div>
        <div class="actions">
            <button class="btn gold" type="submit">{{ $m ? 'Kaydet' : 'Çalışanı ekle' }}</button>
            <a class="btn" href="{{ route('panel.staff') }}">Vazgeç</a>
        </div>
    </form>
@endsection
