@extends('layouts.site')

@section('content')
<section class="section">
    <div class="wrap metin">
        <h1>Yeni şifre belirle</h1>

        @if ($done)
            <div class="basarili" role="status">
                <p>Şifren güncellendi. {{ $isPanel ? 'Panele yeni şifrenle giriş yapabilirsin.' : 'Kumbare uygulamasına yeni şifrenle giriş yapabilirsin.' }}</p>
            </div>
        @elseif (! $token || ! $email)
            <p class="uyari" role="alert">Bu bağlantı eksik. {{ $isPanel ? 'Panelin giriş ekranından' : 'Uygulamanın giriş ekranından' }} "Şifremi unuttum" ile yeni bir bağlantı iste.</p>
        @else
            @error('token')<p class="uyari" role="alert">{{ $message }}</p>@enderror
            <form class="form-grid" method="post" action="{{ url()->current() }}" novalidate>
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div class="field full">
                    <label for="email">E-posta</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" readonly>
                </div>
                @include('site.partials.field', ['name' => 'password', 'label' => 'Yeni şifre (en az '.($isPanel ? 10 : 8).' karakter)', 'type' => 'password', 'autocomplete' => 'new-password', 'noOld' => true])
                @include('site.partials.field', ['name' => 'password_confirmation', 'label' => 'Yeni şifre tekrar', 'type' => 'password', 'autocomplete' => 'new-password', 'noOld' => true])
                <div class="full"><button class="btn" type="submit">Şifreyi kaydet</button></div>
            </form>
        @endif
    </div>
</section>
@endsection
