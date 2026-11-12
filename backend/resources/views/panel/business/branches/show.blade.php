@extends('layouts.panel')
@php use App\Support\Format; use App\Support\Labels; $b = $branch; @endphp
@section('title', $b->name)

@section('content')
    <div class="p-head">
        <div>
            <div class="crumb"><a href="{{ route('biz.branches') }}">← Şubeler</a></div>
            <h1>{{ $b->name }}</h1>
            <p>{!! Labels::badge('branch', $b->status) !!} {{ $b->district?->name }}, {{ $b->city?->name }} · eklenme {{ Labels::date($b->created_at) }}</p>
        </div>
        <div class="actions">
            @if ($b->status === 'active')
                <a class="btn" href="{{ route('web.branch', [$business->slug, $b->slug]) }}" target="_blank" rel="noopener">Sitede gör ↗</a>
            @endif
            <form method="post" action="{{ route('biz.branches.status', $b->id) }}">
                @csrf
                <input type="hidden" name="status" value="{{ $b->status === 'active' ? 'passive' : 'active' }}">
                <button class="btn {{ $b->status === 'active' ? 'danger' : 'gold' }}" type="submit">{{ $b->status === 'active' ? 'Pasif yap' : 'Aktif yap' }}</button>
            </form>
        </div>
    </div>

    <div class="stats three">
        <div class="stat"><div class="k">Müşteri</div><div class="v">{{ Format::number((int) $stats->customers) }}</div><div class="s">son 30 günde {{ Format::number((int) $stats->tx30) }} işlem</div></div>
        <div class="stat"><div class="k">Bu ay müşteri</div><div class="v">{{ Format::number($counter['unique_customers']) }}@if ($counter['limit']) <span class="muted" style="font-size:1rem">/ {{ Format::number($counter['limit']) }}</span>@endif</div>
            <div class="s">@if ($subscription)Profesyonel · bitiş {{ Labels::date($subscription->ends_at) }} @else Ücretsiz paket @endif</div></div>
        <div class="stat gold"><div class="k">Kumbaralardaki bakiye</div><div class="v">{{ Format::money($stats->balance, false) }}</div><div class="s">TL</div></div>
    </div>

    <div class="grid two" style="align-items:start">
        <div class="card">
            <h2>Şube</h2>
            <dl class="kv">
                <dt>Ödül oranı</dt><dd>{{ Format::rate($b->reward_rate) }}@if ((float) $b->reward_rate == 0) <span class="badge warn">Ödül yok</span>@endif</dd>
                <dt>Yayındaki kampanya</dt><dd>{{ Format::number((int) $stats->campaigns) }}</dd>
                <dt>Puan</dt><dd>@if ($b->rating_count > 0){{ Format::score($b->rating_avg) }} / 10 — {{ $b->rating_count }} değerlendirme @else Henüz yok @endif</dd>
                <dt>Adres</dt><dd>{{ $b->address }}</dd>
                <dt>Telefon</dt><dd>{{ $b->phone ?: '—' }}</dd>
                <dt>Çalışma saatleri</dt><dd>{{ $b->hours->isEmpty() ? 'Girilmemiş' : $b->hours->count().' gün tanımlı' }}</dd>
                <dt>Fotoğraf</dt><dd>{{ $b->photos->count() }}</dd>
            </dl>
            <p class="muted small" style="margin-top:12px">Oran, kampanya, saatler, fotoğraflar ve çalışanlar şube yöneticisinin panelinden yönetilir.</p>
        </div>
        <div class="card">
            <h2>Şube yöneticisi</h2>
            @if ($b->manager)
                <dl class="kv">
                    <dt>Ad soyad</dt><dd>{{ $b->manager->fullName() }}</dd>
                    <dt>E-posta</dt><dd>{{ $b->manager->email }}</dd>
                    <dt>Son giriş</dt><dd>{{ $b->manager->last_login_at ? Labels::dateTime($b->manager->last_login_at) : 'Hiç giriş yapmadı' }}</dd>
                    <dt>Aktif çalışan</dt><dd>{{ $staffCount }}</dd>
                </dl>
                <a class="btn sm" style="margin-top:12px" href="{{ route('biz.managers.edit', $b->manager->id) }}">Yöneticiyi düzenle</a>
            @else
                <p>Bu şubenin yöneticisi yok; personel işlem yapamaz.</p>
                <a class="btn gold" href="{{ route('biz.managers.create', ['sube' => $b->id]) }}">Şube yöneticisi ekle</a>
            @endif
        </div>
    </div>

    @unless ($hasTransactions)
        <div class="card">
            <h2>Şubeyi sil</h2>
            <p class="muted">Bu şubede henüz hiç işlem yapılmadığı için silinebilir. İşlem yapılmış şubeler silinemez, pasif yapılır.</p>
            @include('admin.partials.reason-action', [
                'action' => route('biz.branches.destroy', $b->id), 'label' => 'Şubeyi sil', 'btn' => 'danger', 'align' => 'left',
                'required' => false, 'title' => 'Not', 'hint' => 'Şube ve şubeye bağlı panel hesapları kapatılır. Bu işlem geri alınamaz.',
            ])
        </div>
    @endunless
@endsection
