@extends('layouts.admin')
@php use App\Support\Format; use App\Support\Labels; @endphp
@section('title', 'Numara sorguları')

@section('content')
    <div class="p-head">
        <div>
            <div class="crumb"><a href="{{ route('admin.audit') }}">← Denetim kayıtları</a></div>
            <h1>Olağandışı müşteri numarası sorguları</h1>
            <p>Müşteri numaraları sıralı olduğu için personel numaraları tek tek deneyerek ad soyad listesi çıkarabilir.
               Çok sayıda farklı müşteri sorgulayıp az işlem yapan personel şüphelidir.</p>
        </div>
    </div>

    <nav class="tabs" aria-label="Süre">
        @foreach ([1 => 'Son 24 saat', 7 => 'Son 7 gün', 30 => 'Son 30 gün'] as $d => $label)
            <a href="{{ route('admin.audit.lookups', ['gun' => $d]) }}" @if ($days === $d) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    <div class="card flush">
        @if (empty($rows))
            <p class="empty">Bu sürede müşteri numarası sorgusu yok.</p>
        @else
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th>Personel</th><th>Şube</th><th class="n">Sorgu</th><th class="n">Farklı müşteri</th><th class="n">Yaptığı işlem</th><th>Değerlendirme</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($rows as $r)
                        @php
                            $u = $users[$r->actor_id] ?? null;
                            $ratio = $r->transactions > 0 ? $r->customers / $r->transactions : $r->customers;
                            $suspicious = $r->customers >= 20 && $ratio >= 3;
                        @endphp
                        <tr>
                            <td>{{ $u?->fullName() ?? '#'.$r->actor_id }}<span class="sub">{{ $u?->email }} · {{ $u ? Labels::text('role', $u->role) : '' }}</span></td>
                            <td>@if ($u?->branch){{ $u->branch->business->name }} – {{ $u->branch->name }}@else<span class="muted">—</span>@endif</td>
                            <td class="n">{{ Format::number((int) $r->lookups) }}</td>
                            <td class="n">{{ Format::number((int) $r->customers) }}</td>
                            <td class="n">{{ Format::number((int) $r->transactions) }}</td>
                            <td>@if ($suspicious)<span class="badge bad">İncelenmeli</span>@else<span class="badge ok">Olağan</span>@endif</td>
                            <td>@if ($u)<a class="btn sm" href="{{ route('admin.audit', ['islem' => 'customer.lookup', 'kisi' => $u->email]) }}">Sorgular</a>@endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <p class="muted small" style="padding:0 20px 16px">"İncelenmeli": en az 20 farklı müşteri sorgulanmış ve sorgulanan müşteri sayısı yapılan işlem sayısının en az 3 katı.</p>
        @endif
    </div>
@endsection
