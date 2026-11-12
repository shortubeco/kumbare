{{-- Şubenin havale bildirimleri ve sonuçları --}}
@php use App\Support\Format; use App\Support\Labels; @endphp
<div class="card flush">
    <h2>Havale bildirimleriniz</h2>
    @if ($history->isEmpty())
        <p class="empty">Henüz havale bildirimi göndermediniz.</p>
    @else
        <div class="tbl-wrap">
            <table class="tbl">
                <thead><tr><th>Tarih</th><th>Ne için</th><th class="n">Tutar</th><th>Havale tarihi</th><th>Durum</th><th>Not</th></tr></thead>
                <tbody>
                @foreach ($history as $p)
                    <tr>
                        <td class="nowrap">{{ Labels::dateTime($p->created_at) }}</td>
                        <td>
                            {{ Labels::text('purpose', $p->purpose) }}
                            <span class="sub">{{ $p->purpose === 'subscription' ? Labels::text('cycle', $p->billing_cycle) : Labels::month($p->sponsorship_month) }}</span>
                        </td>
                        <td class="n money">{{ Format::money($p->amount) }}</td>
                        <td class="nowrap">{{ Labels::date($p->transfer_date) }}
                            @if ($p->receipt_path)<span class="sub"><a href="{{ route('panel.payment.receipt', $p) }}" target="_blank" rel="noopener">Dekont</a></span>@endif
                        </td>
                        <td>{!! Labels::badge('payment', $p->status) !!}
                            @if ($p->reviewed_at)<span class="sub">{{ Labels::date($p->reviewed_at) }}</span>@endif
                        </td>
                        <td class="small">{{ $p->review_note ?: '' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
