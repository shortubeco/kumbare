@extends('layouts.auth')
@section('title', 'Bu sayfaya erişiminiz yok')

@php
    $user = auth('web')->user();
    $roles = [
        'platform_admin' => 'platform yöneticisi',
        'business_owner' => 'işletme yöneticisi',
        'branch_manager' => 'şube yöneticisi',
        'staff' => 'çalışan',
    ];
    $msg = isset($exception) && $exception->getMessage() ? $exception->getMessage() : 'Bu sayfayı görüntüleme yetkiniz yok.';
@endphp

@section('content')
    <h1>Bu sayfaya erişiminiz yok</h1>
    <p class="lead">{{ $msg }}</p>

    @if ($user instanceof \App\Models\PanelUser)
        <div class="flash info">
            Şu an <b>{{ $user->fullName() }}</b> ({{ $user->email }}) olarak, <b>{{ $roles[$user->role] ?? 'panel kullanıcısı' }}</b> hesabıyla giriş yaptınız.
        </div>
        <div class="actions" style="flex-direction:column;align-items:stretch">
            <a class="btn primary" href="{{ route('panel.home') }}">Kendi panelime dön</a>
            <form method="post" action="{{ route('panel.logout') }}">
                @csrf
                <button class="btn" type="submit" style="width:100%">Çıkış yap ve başka hesapla gir</button>
            </form>
        </div>
    @else
        <a class="btn primary" href="{{ route('panel.login') }}">Giriş yap</a>
    @endif
@endsection
