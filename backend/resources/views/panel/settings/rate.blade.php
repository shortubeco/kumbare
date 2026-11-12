@extends('layouts.panel')
@php use App\Support\Format; use App\Support\Labels; @endphp
@section('title', 'Ödül oranı')

@section('content')
    <div class="p-head">
        <div>
            <h1>Ödül oranı</h1>
            <p>Müşterinin ödediği tutarın yüzde kaçı bu şubenin kumbarasına ödül olarak eklenir. Oran yalnızca bu şube içindir.</p>
        </div>
    </div>

    <div class="grid two" style="align-items:start">
        <form class="card" method="post" action="{{ route('panel.rate.update') }}" novalidate>
            @csrf
            <div class="field">
                <label for="reward_rate">Ödül oranı (%)</label>
                <div class="tx-find-row" style="align-items:center">
                    <span class="score" style="font-size:1.6rem">%</span>
                    <input id="reward_rate" class="big-input" name="reward_rate" type="text" inputmode="decimal" style="max-width:180px"
                           value="{{ old('reward_rate', rtrim(rtrim(number_format((float) $branch->reward_rate, 2, ',', ''), '0'), ',')) }}" required>
                </div>
                <span class="hint">0 ile 100 arasında; ondalık kullanılabilir (örn. 7,5). %0 seçilirse sitede "Ödül yok" görünür.</span>
                @error('reward_rate')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="tx-summary" id="ornek">
                <div class="line"><span>Örnek: 1.000,00 TL harcama</span><b id="ornek-odul">{{ Format::money(1000 * $branch->reward_rate / 100) }} ödül</b></div>
            </div>
            <div class="field">
                <label for="reward_terms">Kullanım koşulları <span class="muted">(isteğe bağlı)</span></label>
                <textarea id="reward_terms" name="reward_terms" maxlength="2000" placeholder="Örn. Ödül, içecek dahil tüm menüde kullanılabilir. Paket serviste geçerli değildir.">{{ old('reward_terms', $branch->reward_terms) }}</textarea>
                <span class="hint">Sitede ve uygulamada şube sayfasında gösterilir.</span>
            </div>
            <button class="btn primary" type="submit">Kaydet</button>
            <p class="muted small" style="margin:12px 0 0">Yeni oran kaydedildiği andan sonraki işlemlerde geçerli olur; geçmiş işlemler ve kumbaradaki bakiyeler değişmez. İşlem ekranında oran kampanyası seçilirse o kampanyanın oranı kullanılır (oranlar toplanmaz).</p>
        </form>

        <div class="card flush">
            <h2>Oran değişiklikleri</h2>
            @if ($history->isEmpty())
                <p class="empty">Henüz değişiklik yok.</p>
            @else
                <div class="tbl-wrap">
                    <table class="tbl">
                        <thead><tr><th>Tarih</th><th class="n">Eski</th><th class="n">Yeni</th><th>Değiştiren</th></tr></thead>
                        <tbody>
                        @foreach ($history as $h)
                            <tr>
                                <td class="nowrap">{{ Labels::dateTime($h->created_at) }}</td>
                                <td class="n">{{ Format::rate($h->old_rate) }}</td>
                                <td class="n"><b>{{ Format::rate($h->new_rate) }}</b></td>
                                <td class="small">{{ $h->changer?->fullName() }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        var i = document.getElementById('reward_rate'), o = document.getElementById('ornek-odul');
        if (!i || !o) return;
        i.addEventListener('input', function () {
            var r = parseFloat(String(i.value).replace(',', '.'));
            o.textContent = isNaN(r) || r < 0 || r > 100 ? '—'
                : new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(1000 * r / 100) + ' TL ödül';
        });
    })();
</script>
@endpush
