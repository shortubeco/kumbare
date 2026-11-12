@extends('layouts.auth')
@section('title', 'Panel')

@php
    $roles = ['business_owner' => 'İşletme yöneticisi', 'branch_manager' => 'Şube yöneticisi', 'staff' => 'Çalışan'];
@endphp

@section('content')
    <h1>Merhaba {{ $user->first_name }}</h1>
    <p class="lead">
        {{ $roles[$user->role] ?? 'Panel kullanıcısı' }}
        @if ($user->business) · {{ $user->business->name }} @endif
        @if ($user->branch) – {{ $user->branch->name }} @endif
    </p>

    @if ($user->business && $user->business->status === 'pending')
        <div class="flash info">Başvurunuz inceleniyor. Onaylandığında e-posta ile haber vereceğiz.</div>
    @elseif ($user->business && $user->business->status === 'rejected')
        <div class="flash err">Başvurunuz onaylanmadı.@if ($user->business->rejection_reason) Gerekçe: {{ $user->business->rejection_reason }}@endif</div>
    @elseif ($user->business && in_array($user->business->status, ['suspended', 'closed'], true))
        <div class="flash err">İşletme hesabı şu anda kullanıma kapalı. Ayrıntı için bizimle iletişime geçin.</div>
    @elseif ($user->branch && $user->branch->status !== 'active')
        <div class="flash err">Şubeniz şu anda kullanıma kapalı. Ayrıntı için işletme yöneticinizle veya bizimle iletişime geçin.</div>
    @else
        <div class="flash info">İşletme ve şube paneli hazırlanıyor; çok yakında buradan menü, şube, kampanya ve ödül işlemlerinizi yönetebileceksiniz.</div>
    @endif

    <form method="post" action="{{ route('panel.logout') }}">
        @csrf
        <button class="btn" type="submit">Çıkış yap</button>
    </form>
@endsection
