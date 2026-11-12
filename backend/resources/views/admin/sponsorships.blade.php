@extends('layouts.admin')
@php use App\Support\Format; use App\Support\Labels; @endphp
@section('title', 'Sponsorluklar')

@section('content')
    <div class="p-head">
        <div>
            <h1>Sponsorluklar</h1>
            <p>Sponsorluk ayın 1'inde başlar, sonraki ayın 1'inde biter. Sponsorlu şube sayısında sınır yoktur.</p>
        </div>
        <form class="filters" method="get" action="{{ route('admin.sponsorships') }}" style="margin:0">
            <div class="field">
                <label for="ay">Ay</label>
                <input id="ay" type="month" name="ay" value="{{ $month->format('Y-m') }}">
            </div>
            <button class="btn" type="submit">Göster</button>
        </form>
    </div>

    <nav class="tabs" aria-label="Ay">
        <a href="{{ route('admin.sponsorships', ['ay' => $month->copy()->subMonth()->format('Y-m')]) }}">← {{ Labels::month($month->copy()->subMonth()) }}</a>
        <a href="{{ route('admin.sponsorships', ['ay' => $month->format('Y-m')]) }}" aria-current="page">{{ Labels::month($month) }}</a>
        <a href="{{ route('admin.sponsorships', ['ay' => $month->copy()->addMonth()->format('Y-m')]) }}">{{ Labels::month($month->copy()->addMonth()) }} →</a>
    </nav>

    <div class="card flush">
        <h2>{{ Labels::month($month) }} sponsorlukları ({{ $sponsorships->count() }})</h2>
        @if ($sponsorships->isEmpty())
            <p class="empty">Bu ay için sponsorluk yok.</p>
        @else
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th>Şube</th><th>Konum</th><th>Ödeme</th><th>Durum</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($sponsorships as $s)
                        <tr>
                            <td><a href="{{ route('admin.businesses.show', $s->branch->business_id) }}">{{ $s->branch->business->name }} – {{ $s->branch->name }}</a></td>
                            <td>{{ $s->branch->district?->name }}, {{ $s->branch->city?->name }}</td>
                            <td>@if ($s->payment_notification_id)<a href="{{ route('admin.payments.show', $s->payment_notification_id) }}">Havale #{{ $s->payment_notification_id }}</a>@else<span class="muted">—</span>@endif</td>
                            <td>{!! Labels::badge('sponsorship', $s->status) !!}</td>
                            <td>
                                @if (in_array($s->status, ['pending', 'active'], true))
                                    @include('admin.partials.reason-action', [
                                        'action' => route('admin.sponsorships.cancel', $s), 'label' => 'İptal et', 'btn' => 'danger',
                                        'title' => 'İptal gerekçesi', 'hint' => 'Şube listelerde sponsorlu görünmez; şube yöneticisine bildirilir.',
                                    ])
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($upcoming->isNotEmpty())
        <div class="card">
            <h2>Sonraki aylar</h2>
            <ul>
                @foreach ($upcoming as $u)
                    <li><a href="{{ route('admin.sponsorships', ['ay' => \Carbon\Carbon::parse($u->month)->format('Y-m')]) }}">{{ Labels::month($u->month) }}</a>: {{ Format::number((int) $u->cnt) }} şube</li>
                @endforeach
            </ul>
        </div>
    @endif
@endsection
