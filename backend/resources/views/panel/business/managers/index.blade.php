@extends('layouts.panel')
@php use App\Support\Labels; @endphp
@section('title', 'Şube yöneticileri')

@section('content')
    <div class="p-head">
        <div>
            <h1>Şube yöneticileri</h1>
            <p>Her şubenin tek bir aktif yöneticisi olur. Yönetici kendi şubesinin ödül oranını, kampanyalarını, çalışanlarını ve işlemlerini yönetir. Yöneticiyi değiştirmek için eskisini pasifleştirip yenisini ekleyin.</p>
        </div>
        <a class="btn gold" href="{{ route('biz.managers.create') }}">Yönetici ekle</a>
    </div>

    @php $missing = $branches->filter(fn ($b) => ! $b->manager); @endphp
    @if ($missing->isNotEmpty())
        <div class="flash info">
            Yöneticisi olmayan şubeler: @foreach ($missing as $b)<a href="{{ route('biz.managers.create', ['sube' => $b->id]) }}">{{ $b->name }}</a>@if (! $loop->last), @endif @endforeach
            — bu şubelerde işlem yapılamaz.
        </div>
    @endif

    <div class="card flush">
        @if ($managers->isEmpty())
            <p class="empty">Henüz şube yöneticisi yok.</p>
        @else
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th>Yönetici</th><th>Şube</th><th>Son giriş</th><th>Durum</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($managers as $m)
                        <tr>
                            <td><strong>{{ $m->fullName() }}</strong><span class="sub">{{ $m->email }}@if ($m->phone) · {{ $m->phone }}@endif</span></td>
                            <td>@if ($m->branch)<a href="{{ route('biz.branches.show', $m->branch_id) }}">{{ $m->branch->name }}</a>@if ($m->branch->status !== 'active') {!! Labels::badge('branch', $m->branch->status) !!}@endif @endif</td>
                            <td class="small nowrap">{{ $m->last_login_at ? Labels::dateTime($m->last_login_at) : 'Hiç giriş yapmadı' }}</td>
                            <td>{!! Labels::badge('customer', $m->status) !!}</td>
                            <td class="nowrap">
                                <div class="actions">
                                    <a class="btn sm" href="{{ route('biz.managers.edit', $m->id) }}">Düzenle</a>
                                    @if ($m->branch?->status !== 'deleted')
                                        <form method="post" action="{{ route('biz.managers.toggle', $m->id) }}">
                                            @csrf
                                            <button class="btn sm {{ $m->status === 'active' ? 'danger' : '' }}" type="submit">{{ $m->status === 'active' ? 'Pasifleştir' : 'Aktif et' }}</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
