@extends('layouts.admin')
@php use App\Support\Labels; @endphp
@section('title', 'Denetim kayıtları')

@section('content')
    <div class="p-head">
        <div>
            <h1>Denetim kayıtları</h1>
            <p>Girişler, müşteri numarası sorguları, işlemler ve yönetim kararları. Kayıtlar silinmez.</p>
        </div>
        <a class="btn" href="{{ route('admin.audit.lookups') }}">Olağandışı numara sorguları →</a>
    </div>

    <form class="filters" method="get" action="{{ route('admin.audit') }}">
        <div class="field">
            <label for="islem">İşlem</label>
            <select id="islem" name="islem">
                <option value="">Tümü</option>
                @foreach ($actions as $a)
                    <option value="{{ $a }}" @selected($filters['islem'] === $a)>{{ $a }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="kisi">Panel kullanıcısı e-postası</label>
            <input id="kisi" type="search" name="kisi" value="{{ $filters['kisi'] }}">
        </div>
        <div class="field">
            <label for="baslangic">Başlangıç</label>
            <input id="baslangic" type="date" name="baslangic" value="{{ $filters['baslangic'] }}">
        </div>
        <div class="field">
            <label for="bitis">Bitiş</label>
            <input id="bitis" type="date" name="bitis" value="{{ $filters['bitis'] }}">
        </div>
        <button class="btn primary" type="submit">Filtrele</button>
        @if (array_filter($filters))<a class="btn" href="{{ route('admin.audit') }}">Temizle</a>@endif
    </form>

    <div class="card flush">
        @if ($logs->isEmpty())
            <p class="empty">Kayıt yok.</p>
        @else
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th>Zaman</th><th>Kim</th><th>İşlem</th><th>Kayıt</th><th>Ayrıntı</th><th>IP</th></tr></thead>
                    <tbody>
                    @foreach ($logs as $log)
                        <tr>
                            <td class="nowrap">{{ Labels::dateTime($log->created_at) }}</td>
                            <td>
                                @if ($log->actor_type === 'panel_user' && isset($actors[$log->actor_id]))
                                    {{ $actors[$log->actor_id]->fullName() }}
                                    <span class="sub">{{ Labels::text('role', $actors[$log->actor_id]->role) }}</span>
                                @elseif ($log->actor_type === 'customer')
                                    Müşteri <a href="{{ route('admin.customers.show', $log->actor_id) }}">#{{ $log->actor_id }}</a>
                                @elseif ($log->actor_type === 'system')
                                    <span class="muted">Sistem</span>
                                @else
                                    {{ $log->actor_type }} #{{ $log->actor_id }}
                                @endif
                            </td>
                            <td><code>{{ $log->action }}</code></td>
                            <td class="nowrap">{{ $log->subject_type }}@if ($log->subject_id) #{{ $log->subject_id }}@endif</td>
                            <td style="max-width:380px" class="small">
                                @if ($log->payload)
                                    <code style="white-space:pre-wrap;word-break:break-word">{{ \Illuminate\Support\Str::limit(json_encode($log->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 300) }}</code>
                                @endif
                            </td>
                            <td class="small nowrap">{{ $log->ip }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @include('site.partials.pagination', ['paginator' => $logs])
        @endif
    </div>
@endsection
