@extends('layouts.panel')
@php use App\Support\Format; use App\Support\Labels; @endphp
@section('title', 'Çalışanlar')

@section('content')
    <div class="p-head">
        <div>
            <h1>Çalışanlar</h1>
            <p>Çalışanlar yalnızca bu şubeye bağlıdır ve işaretlediğiniz yetkilerle işlem yapar. Hesaplar silinmez; ayrılan çalışanı pasifleştirin.</p>
        </div>
        <a class="btn gold" href="{{ route('panel.staff.create') }}">Çalışan ekle</a>
    </div>

    <div class="card flush">
        @if ($staff->isEmpty())
            <p class="empty">Henüz çalışan eklenmemiş. İşlem ekranını siz de kullanabilirsiniz; kasada başka biri çalışıyorsa <a href="{{ route('panel.staff.create') }}">çalışan ekleyin</a>.</p>
        @else
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th>Çalışan</th><th>Yetkiler</th><th class="n">İşlem (30 gün)</th><th>Son giriş</th><th>Durum</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($staff as $s)
                        <tr>
                            <td><strong>{{ $s->fullName() }}</strong><span class="sub">{{ $s->email }}@if ($s->phone) · {{ $s->phone }}@endif</span></td>
                            <td class="small">
                                @php $perms = $s->permissions->pluck('permission'); @endphp
                                @forelse ($perms as $p)
                                    <span class="badge info" style="margin:1px 0">{{ $permissions[$p][0] ?? $p }}</span>
                                @empty
                                    <span class="muted">Yetki yok</span>
                                @endforelse
                            </td>
                            <td class="n">{{ Format::number((int) ($txCounts[$s->id] ?? 0)) }}</td>
                            <td class="nowrap small">{{ $s->last_login_at ? Labels::dateTime($s->last_login_at) : 'Hiç giriş yapmadı' }}</td>
                            <td>{!! Labels::badge('customer', $s->status) !!}</td>
                            <td class="nowrap">
                                <div class="actions">
                                    <a class="btn sm" href="{{ route('panel.staff.edit', $s->id) }}">Düzenle</a>
                                    <form method="post" action="{{ route('panel.staff.toggle', $s->id) }}">
                                        @csrf
                                        <button class="btn sm {{ $s->status === 'active' ? 'danger' : '' }}" type="submit">{{ $s->status === 'active' ? 'Pasifleştir' : 'Aktif et' }}</button>
                                    </form>
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
