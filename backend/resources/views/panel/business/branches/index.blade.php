@extends('layouts.panel')
@php use App\Support\Format; use App\Support\Labels; @endphp
@section('title', 'Şubeler')

@section('content')
    <div class="p-head">
        <div>
            <h1>Şubeler</h1>
            <p>Her şube bağımsızdır: kendi kumbarası, ödül oranı, aylık müşteri sayacı ve paketi vardır. Ödül oranını ve çalışanları şube yöneticisi belirler.</p>
        </div>
        <a class="btn gold" href="{{ route('biz.branches.create') }}">Şube ekle</a>
    </div>

    <div class="card flush">
        @if (empty($rows))
            <p class="empty">Henüz şube yok. <a href="{{ route('biz.branches.create') }}">İlk şubenizi ekleyin</a>.</p>
        @else
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th>Şube</th><th>Yönetici</th><th class="n">Oran</th><th>Paket</th><th class="n">Müşteri</th><th class="n">Bakiye</th><th>Durum</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($rows as $r)
                        <tr>
                            <td><a class="row-link" href="{{ route('biz.branches.show', $r->id) }}">{{ $r->name }}</a><span class="sub">{{ $r->district }}, {{ $r->city }}</span></td>
                            <td>@if ($r->manager){{ $r->manager }}@else<a class="btn sm gold" href="{{ route('biz.managers.create', ['sube' => $r->id]) }}">Yönetici ekle</a>@endif</td>
                            <td class="n">{{ Format::rate($r->reward_rate) }}</td>
                            <td>@if ($r->pro_until)<span class="badge pro">Profesyonel</span><span class="sub">bitiş {{ Labels::date($r->pro_until) }}</span>@else<span class="badge">Ücretsiz</span>@endif</td>
                            <td class="n">{{ Format::number((int) $r->customers) }}</td>
                            <td class="n money">{{ Format::money($r->balance) }}</td>
                            <td>{!! Labels::badge('branch', $r->status) !!}</td>
                            <td class="nowrap">
                                <form method="post" action="{{ route('biz.branches.status', $r->id) }}">
                                    @csrf
                                    <input type="hidden" name="status" value="{{ $r->status === 'active' ? 'passive' : 'active' }}">
                                    <button class="btn sm {{ $r->status === 'active' ? 'danger' : '' }}" type="submit">{{ $r->status === 'active' ? 'Pasif yap' : 'Aktif yap' }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
    <p class="muted small">Pasif şube sitede ve uygulamada görünmez, personel işlem yapamaz; müşteri bakiyeleri korunur ve şube yeniden aktif edildiğinde kullanılabilir.</p>
@endsection
