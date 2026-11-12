@extends('layouts.panel')
@php $m = $member; @endphp
@section('title', $m ? 'Yöneticiyi düzenle' : 'Şube yöneticisi ekle')

@section('content')
    <div class="p-head">
        <div>
            <div class="crumb"><a href="{{ route('biz.managers') }}">← Şube yöneticileri</a></div>
            <h1>{{ $m ? $m->fullName() : 'Şube yöneticisi ekle' }}</h1>
            <p>{{ $m ? '"'.$m->branch?->name.'" şubesinin yöneticisi.' : 'Yönetici, burada belirlediğiniz e-posta ve şifreyle panele giriş yapar.' }}</p>
        </div>
    </div>

    @if (! $m && $branches->isEmpty())
        <div class="card">
            <p>Yöneticisi olmayan şube yok. Yeni yönetici eklemek için önce <a href="{{ route('biz.branches.create') }}">şube ekleyin</a> ya da bir şubenin mevcut yöneticisini <a href="{{ route('biz.managers') }}">pasifleştirin</a>.</p>
        </div>
    @else
    <form class="card" method="post" action="{{ $m ? route('biz.managers.update', $m->id) : route('biz.managers.store') }}" novalidate style="max-width:680px">
        @csrf
        @unless ($m)
            <div class="field">
                <label for="branch_id">Şube</label>
                <select id="branch_id" name="branch_id" required @error('branch_id') aria-invalid="true" @enderror>
                    @foreach ($branches as $b)
                        <option value="{{ $b->id }}" @selected((int) old('branch_id', $selected) === $b->id)>{{ $b->name }}</option>
                    @endforeach
                </select>
                <span class="hint">Yalnızca aktif yöneticisi olmayan şubeler listelenir.</span>
                @error('branch_id')<span class="field-error">{{ $message }}</span>@enderror
            </div>
        @endunless
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
            <span class="hint muted small">En az 10 karakter. Şifreyi yöneticiye kendiniz iletin; daha sonra "Şifremi unuttum" ile değiştirebilir.</span>
        </fieldset>
        <div class="actions" style="margin-top:14px">
            <button class="btn gold" type="submit">{{ $m ? 'Kaydet' : 'Yöneticiyi ekle' }}</button>
            <a class="btn" href="{{ route('biz.managers') }}">Vazgeç</a>
        </div>
    </form>
    @endif
@endsection
