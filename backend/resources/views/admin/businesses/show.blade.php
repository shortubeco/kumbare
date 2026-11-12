@extends('layouts.admin')
@php use App\Support\Format; use App\Support\Labels; @endphp
@section('title', $business->name)

@section('content')
    <div class="p-head">
        <div>
            <div class="crumb"><a href="{{ route('admin.businesses') }}">← İşletmeler</a></div>
            <h1>{{ $business->name }}</h1>
            <p>{!! Labels::badge('business', $business->status) !!} · {{ $business->category?->name ?? 'Mutfak seçilmemiş' }} · Kayıt {{ Labels::date($business->created_at) }}</p>
        </div>
        <div class="actions">
            @if ($business->status === 'pending')
                <form method="post" action="{{ route('admin.businesses.approve', $business) }}">
                    @csrf
                    <button class="btn gold" type="submit">Başvuruyu onayla</button>
                </form>
                @include('admin.partials.reason-action', [
                    'action' => route('admin.businesses.reject', $business), 'label' => 'Reddet', 'btn' => 'danger',
                    'title' => 'Ret gerekçesi', 'hint' => 'Gerekçe işletmeye e-postayla iletilir.',
                ])
            @elseif ($business->status === 'approved')
                @if ($business->branches()->where('status', 'active')->exists())
                    <a class="btn" href="{{ route('web.branch', [$business->slug, $business->branches()->where('status', 'active')->value('slug')]) }}" target="_blank" rel="noopener">Sitede gör ↗</a>
                @endif
                @include('admin.partials.reason-action', [
                    'action' => route('admin.businesses.status', $business), 'label' => 'Askıya al', 'btn' => 'danger',
                    'hidden' => ['status' => 'suspended'], 'required' => false,
                    'title' => 'Askıya alma gerekçesi', 'hint' => 'Şubeler sitede ve uygulamada görünmez; personel işlem yapamaz. Bakiyeler korunur.',
                ])
                @include('admin.partials.reason-action', [
                    'action' => route('admin.businesses.status', $business), 'label' => 'Kapat', 'btn' => 'danger',
                    'hidden' => ['status' => 'closed'], 'required' => false,
                    'title' => 'Kapatma gerekçesi', 'hint' => 'İşletme kalıcı olarak kapatılır; kayıtlar silinmez ve gerekirse yeniden açılabilir.',
                ])
            @elseif (in_array($business->status, ['suspended', 'closed'], true))
                @include('admin.partials.reason-action', [
                    'action' => route('admin.businesses.status', $business), 'label' => 'Yeniden aktif et',
                    'hidden' => ['status' => 'approved'], 'required' => false, 'title' => 'Not',
                ])
            @endif
        </div>
    </div>

    @if ($business->status === 'rejected' && $business->rejection_reason)
        <div class="flash err">Ret gerekçesi: {{ $business->rejection_reason }}</div>
    @endif

    <div class="grid two">
        <div class="card">
            <h2>İşletme bilgileri</h2>
            <dl class="kv">
                <dt>Ticari unvan</dt><dd>{{ $business->legal_name ?: '—' }}</dd>
                <dt>Vergi dairesi</dt><dd>{{ $business->tax_office ?: '—' }}</dd>
                <dt>Vergi numarası</dt><dd>{{ $business->tax_number ?: '—' }}</dd>
                <dt>Açıklama</dt><dd>{{ $business->description ?: '—' }}</dd>
                @if ($business->approved_at)
                    <dt>Onay tarihi</dt><dd>{{ Labels::dateTime($business->approved_at) }}</dd>
                @endif
            </dl>
        </div>
        <div class="card">
            <h2>İşletme yöneticisi</h2>
            @if ($business->owner)
                <dl class="kv">
                    <dt>Ad soyad</dt><dd>{{ $business->owner->fullName() }}</dd>
                    <dt>E-posta</dt><dd><a href="mailto:{{ $business->owner->email }}">{{ $business->owner->email }}</a></dd>
                    <dt>Telefon</dt><dd>{{ $business->owner->phone ?: '—' }}</dd>
                    <dt>Hesap</dt><dd>{!! Labels::badge('customer', $business->owner->status) !!}</dd>
                    <dt>Son giriş</dt><dd>{{ $business->owner->last_login_at ? Labels::dateTime($business->owner->last_login_at) : 'Hiç giriş yapmadı' }}</dd>
                </dl>
            @else
                <p class="muted">Yönetici hesabı yok.</p>
            @endif
        </div>
    </div>

    <div class="card flush">
        <div class="card-head">
            <h2>Şubeler ({{ $branchRows->count() }})</h2>
            <span class="muted small">Ücretsiz paket limiti: ayda {{ Format::number($limit) }} farklı müşteri</span>
        </div>
        @if ($branchRows->isEmpty())
            <p class="empty">İşletme henüz şube eklememiş.</p>
        @else
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                    <tr><th>Şube</th><th>Yönetici</th><th class="n">Ödül</th><th>Paket</th><th>Bu ay müşteri</th><th class="n">Müşteri</th><th class="n">Bakiye</th><th>Durum</th><th></th></tr>
                    </thead>
                    <tbody>
                    @foreach ($branchRows as $row)
                        @php
                            $b = $row['branch'];
                            $sub = $row['subscription'];
                            $pct = $limit > 0 ? min(100, $row['monthly'] * 100 / $limit) : 0;
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $b->name }}</strong>
                                <span class="sub">{{ $b->district?->name }}, {{ $b->city?->name }}</span>
                            </td>
                            <td>
                                @if ($b->manager){{ $b->manager->fullName() }}<span class="sub">{{ $b->manager->email }}</span>@else<span class="muted">—</span>@endif
                            </td>
                            <td class="n">{{ Format::rate($b->reward_rate) }}</td>
                            <td>
                                @if ($sub)
                                    <span class="badge pro">Profesyonel</span>
                                    <span class="sub">{{ Labels::date($sub->ends_at) }} tarihine kadar</span>
                                @else
                                    <span class="badge">Ücretsiz</span>
                                @endif
                            </td>
                            <td>
                                <span class="num">{{ Format::number($row['monthly']) }}</span>@unless ($sub)<span class="muted"> / {{ Format::number($limit) }}</span>@endunless
                                @unless ($sub)
                                    <div class="meter {{ $pct >= 100 ? 'full' : ($pct >= 80 ? 'warn' : '') }}"><span style="width: {{ $pct }}%"></span></div>
                                @endunless
                            </td>
                            <td class="n">{{ Format::number($row['customers']) }}</td>
                            <td class="n money">{{ Format::money($row['balance']) }}</td>
                            <td>{!! Labels::badge('branch', $b->status) !!}</td>
                            <td class="nowrap">
                                @if ($b->status === 'active')
                                    @include('admin.partials.reason-action', [
                                        'action' => route('admin.branches.status', $b), 'label' => 'Pasif yap', 'btn' => 'danger',
                                        'hidden' => ['status' => 'passive'], 'required' => false, 'title' => 'Gerekçe',
                                        'hint' => 'Şube sitede görünmez ve işlem yapamaz; şube yöneticisine bildirilir.',
                                    ])
                                @elseif ($b->status === 'passive')
                                    <form method="post" action="{{ route('admin.branches.status', $b) }}">
                                        @csrf
                                        <input type="hidden" name="status" value="active">
                                        <button class="btn sm" type="submit">Aktif yap</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
