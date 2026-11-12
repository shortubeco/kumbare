@extends('layouts.panel')
@php use App\Support\Format; use App\Support\Labels; @endphp
@section('title', 'Müşteri '.$customer->customer_no)

@section('content')
    <div class="p-head">
        <div>
            <div class="crumb"><a href="{{ route('panel.customers') }}">← Müşteriler</a></div>
            <h1>{{ $customer->fullName() }} <span class="muted num">#{{ $customer->customer_no }}</span></h1>
            <p>
                @if ($customer->trashed()) <span class="badge">Hesabını sildi</span>
                @elseif ($customer->status === 'passive') <span class="badge warn">Hesap pasif</span>
                @endif
                Şubenizdeki ilk işlem: {{ Labels::date($wallet->created_at) }}
            </p>
        </div>
        <a class="btn gold" href="{{ route('panel.tx.screen') }}">İşlem ekranı</a>
    </div>

    <div class="stats three">
        <div class="stat gold"><div class="k">Bakiye</div><div class="v">{{ Format::money($wallet->balance, false) }}</div><div class="s">TL · bu şubede kullanılabilir</div></div>
        <div class="stat"><div class="k">Toplam kazanılan</div><div class="v">{{ Format::money($wallet->total_earned, false) }}</div><div class="s">TL</div></div>
        <div class="stat"><div class="k">Toplam kullanılan</div><div class="v">{{ Format::money($wallet->total_redeemed, false) }}</div><div class="s">TL</div></div>
    </div>

    @if ($pending->isNotEmpty())
        <div class="card">
            <h2>Müşteri onayı bekleyen istekler</h2>
            <ul>
                @foreach ($pending as $p)
                    <li>
                        {{ $p->type === 'redeem' ? 'Ödül kullanımı' : 'Düzeltme' }}:
                        <b class="money">{{ $p->amount > 0 ? '+' : '' }}{{ Format::money($p->amount) }}</b>
                        · {{ Labels::dateTime($p->created_at) }}
                        @if ($p->reason)<span class="muted">· {{ $p->reason }}</span>@endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card flush">
        <div class="card-head">
            <h2>İşlem geçmişi</h2>
            @if ($canCorrect)<span class="muted small">Hatalı bir işlem için satırdaki "Düzelt" ile müşteriye düzeltme isteği gönderin.</span>@endif
        </div>
        @if ($transactions->isEmpty())
            <p class="empty">İşlem yok.</p>
        @else
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th>Tarih</th><th>Tür</th><th class="n">Harcama</th><th class="n">Oran</th><th class="n">Tutar</th><th class="n">Bakiye</th><th>Personel</th>@if ($canCorrect)<th></th>@endif</tr></thead>
                    <tbody>
                    @foreach ($transactions as $t)
                        @php $cors = $corrections[$t->id] ?? collect(); $hasPending = $cors->contains('status', 'pending'); @endphp
                        <tr>
                            <td class="nowrap">{{ Labels::dateTime($t->created_at) }} <span class="sub">#{{ $t->id }}</span></td>
                            <td>
                                {!! Labels::badge('tx', $t->type) !!}
                                @if ($t->campaign)<span class="sub">{{ $t->campaign->title }}</span>@endif
                                @if ($t->type === 'correction' && $t->related_transaction_id)<span class="sub">#{{ $t->related_transaction_id }} için</span>@endif
                                @if ($t->note)<span class="sub">{{ $t->note }}</span>@endif
                                @foreach ($cors as $c)
                                    <span class="sub">Düzeltme {{ $c->amount > 0 ? '+' : '' }}{{ Format::money($c->amount) }}: {{ Labels::text('approval', $c->status) }}</span>
                                @endforeach
                            </td>
                            <td class="n money">{{ $t->spend_amount ? Format::money($t->spend_amount) : '' }}</td>
                            <td class="n">{{ $t->reward_rate !== null ? Format::rate($t->reward_rate) : '' }}</td>
                            <td class="n money">{{ $t->amount > 0 ? '+' : '' }}{{ Format::money($t->amount) }}</td>
                            <td class="n money">{{ Format::money($t->balance_after) }}</td>
                            <td class="small">{{ $t->performer?->fullName() }}</td>
                            @if ($canCorrect)
                                <td class="nowrap">
                                    @if (in_array($t->type, ['earn', 'redeem'], true) && ! $hasPending && ! $customer->trashed())
                                        <a class="btn sm" href="{{ route('panel.corrections.create', ['islem' => $t->id]) }}">Düzelt</a>
                                    @elseif ($hasPending)
                                        <span class="badge warn">Düzeltme bekliyor</span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @include('site.partials.pagination', ['paginator' => $transactions])
        @endif
    </div>
@endsection
