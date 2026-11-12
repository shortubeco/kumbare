@extends('layouts.panel')
@php use App\Support\Format; use App\Support\Labels; @endphp
@section('title', 'Düzeltmeler')

@section('content')
    <div class="p-head">
        <div>
            <h1>Düzeltmeler</h1>
            <p>Hatalı işlemler için müşteriye gönderilen düzeltme istekleri. Yeni düzeltme için <a href="{{ route('panel.customers') }}">Müşteriler</a>'den müşteriyi açıp işlemin yanındaki "Düzelt"e basın.</p>
        </div>
    </div>

    <nav class="tabs" aria-label="Duruma göre">
        @foreach (['pending' => 'Onay bekleyen', 'approved' => 'Onaylanan', 'rejected' => 'Reddedilen', 'withdrawn' => 'Geri çekilen', 'all' => 'Tümü'] as $key => $label)
            <a href="{{ route('panel.corrections', ['durum' => $key]) }}" @if ($status === $key) aria-current="page" @endif>{{ $label }}
                @if ($key !== 'all')<span class="c">{{ Format::number((int) ($counts[$key] ?? 0)) }}</span>@endif
            </a>
        @endforeach
    </nav>

    <div class="card flush">
        @if ($requests->isEmpty())
            <p class="empty">{{ $status === 'pending' ? 'Onay bekleyen düzeltme yok.' : 'Kayıt yok.' }}</p>
        @else
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th>Tarih</th><th>Müşteri</th><th>Düzeltilen işlem</th><th class="n">Düzeltme</th><th>Gerekçe</th><th>Durum</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($requests as $r)
                        <tr>
                            <td class="nowrap">{{ Labels::dateTime($r->created_at) }}<span class="sub">{{ $r->requester?->fullName() }}</span></td>
                            <td><a href="{{ route('panel.customers.show', $r->customer->customer_no) }}">{{ $r->customer->fullName() }}</a><span class="sub">#{{ $r->customer->customer_no }}</span></td>
                            <td>
                                @if ($r->relatedTransaction)
                                    {{ Labels::text('tx', $r->relatedTransaction->type) }} #{{ $r->relatedTransaction->id }}
                                    <span class="sub">{{ $r->relatedTransaction->amount > 0 ? '+' : '' }}{{ Format::money($r->relatedTransaction->amount) }} · {{ Labels::date($r->relatedTransaction->created_at) }}</span>
                                @endif
                            </td>
                            <td class="n money">{{ $r->amount > 0 ? '+' : '' }}{{ Format::money($r->amount) }}</td>
                            <td class="small" style="max-width:280px">{{ $r->reason }}</td>
                            <td>
                                {!! Labels::badge('approval', $r->status) !!}
                                @if ($r->failure_reason)<span class="sub">{{ $r->failure_reason }}</span>@endif
                                @if ($r->responded_at)<span class="sub">{{ Labels::dateTime($r->responded_at) }}</span>@endif
                                @if ($r->resultTransaction)<span class="sub">Yeni bakiye {{ Format::money($r->resultTransaction->balance_after) }}</span>@endif
                            </td>
                            <td>
                                @if ($r->status === 'pending')
                                    <form method="post" action="{{ route('panel.corrections.withdraw', $r->id) }}">
                                        @csrf
                                        <button class="btn sm danger" type="submit">Geri çek</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @include('site.partials.pagination', ['paginator' => $requests])
        @endif
    </div>
@endsection
