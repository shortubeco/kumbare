@extends('layouts.auth')
@section('title', 'Panel girişi')

@section('content')
    <h1>Panele giriş</h1>
    <p class="lead">İşletme, şube ve platform yöneticileri ile çalışanlar buradan giriş yapar.</p>

    <form method="post" action="{{ route('panel.login.submit') }}" novalidate>
        @csrf
        @include('site.partials.field', ['name' => 'email', 'label' => 'E-posta', 'type' => 'email', 'autocomplete' => 'username'])
        @include('site.partials.field', ['name' => 'password', 'label' => 'Şifre', 'type' => 'password', 'autocomplete' => 'current-password', 'noOld' => true])
        <div class="field">
            <label class="check"><input type="checkbox" name="remember" value="1"> Bu cihazda oturumum açık kalsın</label>
        </div>
        <button class="btn primary" type="submit">Giriş yap</button>
    </form>

    <div class="auth-links">
        <a href="{{ route('panel.forgot') }}">Şifremi unuttum</a>
        <a href="{{ route('web.business') }}">Restoranınızı ekleyin</a>
    </div>
@endsection
