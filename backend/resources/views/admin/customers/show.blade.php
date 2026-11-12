@extends('layouts.admin')
@php use App\Support\Format; use App\Support\Labels; @endphp
@section('title', 'Müşteri '.$customer->customer_no)

@section('content')
    <div class="p-head">
        <div>
            <div class="crumb"><a href="{{ route('admin.customers') }}">← Müşteriler</a></div>
            <h1>{{ $customer->fullName() }} <span class="muted num">#{{ $customer->customer_no }}</span></h1>
            <p>
                @if ($customer->trashed())
                    <span class="badge">Hesap silindi ({{ Labels::date($customer->deleted_at) }})</span>
                @else
                    {!! Labels::badge('customer', $customer->status) !!}
                @endif
                · Kayıt {{ Labels::date($customer->created_at) }}
            </p>
        </div>
        @unless ($customer->trashed())
            <div class="actions">
                @if ($customer->status === 'active')
                    @include('admin.partials.reason-action', [
                        'action' => route('admin.customers.status', $customer), 'label' => 'Pasifleştir', 'btn' => 'danger',
                        'hidden' => ['status' => 'passive'], 'required' => false, 'title' => 'Gerekçe',
                        'hint' => 'Müşteri uygulamaya giriş yapamaz; bakiyeleri korunur.',
                    ])
                @else
                    <form method="post" action="{{ route('admin.customers.status', $customer) }}">
                        @csrf
                        <input type="hidden" name="status" value="active">
                        <button class="btn" type="submit">Yeniden aktif et</button>
                    </form>
                @endif
            </div>
        @endunless
    </div>

    <div class="grid two">
        <div class="card">
            <h2>Bilgiler</h2>
            <dl class="kv">
                <dt>E-posta</dt><dd>{{ $customer->email }}</dd>
                <dt>Telefon</dt><dd>{{ $customer->phone }}</dd>
                <dt>Kayıtlı konum</dt><dd>{{ $customer->district?->name }}, {{ $customer->city?->name }}</dd>
                <dt>Doğum tarihi</dt><dd>{{ Labels::date($customer->birth_date) }}</dd>
                <dt>Yorumlarda görünen ad</dt><dd>{{ $customer->publicName() }}</dd>
                <dt>KVKK onayı</dt><dd>{{ Labels::dateTime($customer->kvkk_consent_at) }}</dd>
            </dl>
        </div>
        <div class="card">
            <h2>Doğum tarihini düzelt</h2>
            <p class="muted small">Müşteri doğum tarihini kendisi değiştiremez. Hatalı girilmişse buradan düzeltilir; değişiklik denetim kaydına yazılır.</p>
            @unless ($customer->trashed())
                <form method="post" action="{{ route('admin.customers.birth-date', $customer) }}" class="form-grid">
                    @csrf
                    <div class="field">
                        <label for="birth_date">Doğru doğum tarihi</label>
                        <input id="birth_date" type="date" name="birth_date" required min="1900-01-01" max="{{ now()->subDay()->toDateString() }}"
                               value="{{ old('birth_date', $customer->birth_date?->toDateString()) }}">
                    </div>
                    <div class="field">
                        <label for="bd_reason">Gerekçe</label>
                        <input id="bd_reason" type="text" name="reason" required maxlength="1000" value="{{ old('reason') }}" placeholder="Örn. müşteri kimlik gösterdi">
                    </div>
                    <div><button class="btn primary" type="submit">Kaydet</button></div>
                </form>
            @endunless
            @if ($birthDateChanges->isNotEmpty())
                <h3 style="margin-top:14px">Önceki düzeltmeler</h3>
                <ul class="small">
                    @foreach ($birthDateChanges as $log)
                        <li>{{ Labels::dateTime($log->created_at) }}: {{ Labels::date($log->payload['from'] ?? null) }} → {{ Labels::date($log->payload['to'] ?? null) }} ({{ $log->payload['reason'] ?? '' }})</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="card flush">
        <h2>Kumbaraları ({{ $wallets->count() }})</h2>
        @if ($wallets->isEmpty())
            <p class="empty">Henüz hiçbir şubede işlemi yok.</p>
        @else
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th>Şube</th><th class="n">Kazanılan</th><th class="n">Kullanılan</th><th class="n">Bakiye</th><th>Son hareket</th></tr></thead>
                    <tbody>
                    @foreach ($wallets as $w)
                        <tr>
                            <td><a href="{{ route('admin.businesses.show', $w->business_id) }}">{{ $w->business_name }} – {{ $w->branch_name }}</a></td>
                            <td class="n money">{{ Format::money($w->total_earned) }}</td>
                            <td class="n money">{{ Format::money($w->total_redeemed) }}</td>
                            <td class="n money">{{ Format::money($w->balance) }}</td>
                            <td class="nowrap">{{ Labels::dateTime($w->updated_at) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="card flush">
        <h2>Son işlemler</h2>
        @if ($transactions->isEmpty())
            <p class="empty">İşlem yok.</p>
        @else
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th>Tarih</th><th>Şube</th><th>Tür</th><th class="n">Harcama</th><th class="n">Oran</th><th class="n">Tutar</th><th class="n">Bakiye</th></tr></thead>
                    <tbody>
                    @foreach ($transactions as $t)
                        <tr>
                            <td class="nowrap">{{ Labels::dateTime($t->created_at) }}</td>
                            <td>{{ $t->business_name }} – {{ $t->branch_name }}</td>
                            <td>{!! Labels::badge('tx', $t->type) !!}</td>
                            <td class="n money">{{ $t->spend_amount ? Format::money($t->spend_amount) : '' }}</td>
                            <td class="n">{{ $t->reward_rate !== null ? Format::rate($t->reward_rate) : '' }}</td>
                            <td class="n money">{{ $t->amount > 0 ? '+' : '' }}{{ Format::money($t->amount) }}</td>
                            <td class="n money">{{ Format::money($t->balance_after) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
