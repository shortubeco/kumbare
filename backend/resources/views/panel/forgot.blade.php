@extends('layouts.auth')
@section('title', 'Şifremi unuttum')

@section('content')
    <h1>Şifremi unuttum</h1>
    <p class="lead">Panel hesabınızın e-posta adresini yazın; yeni şifre belirlemeniz için bir bağlantı gönderelim.</p>

    <form method="post" action="{{ route('panel.forgot.send') }}" novalidate>
        @csrf
        @include('site.partials.field', ['name' => 'email', 'label' => 'E-posta', 'type' => 'email', 'autocomplete' => 'username'])
        <button class="btn primary" type="submit">Bağlantı gönder</button>
    </form>

    <div class="auth-links">
        <a href="{{ route('panel.login') }}">← Girişe dön</a>
    </div>
@endsection
