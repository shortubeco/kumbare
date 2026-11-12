{{-- Havale bilgileri (platform ayarlarından) --}}
@php $ibanPretty = $bank['iban'] ? trim(chunk_split($bank['iban'], 4, ' ')) : null; @endphp
<div class="card">
    <h2>Havale bilgileri</h2>
    @if (! $bank['iban'])
        <p class="muted">Havale bilgileri henüz eklenmedi. Lütfen kısa bir süre sonra tekrar bakın veya bizimle iletişime geçin.</p>
    @else
        <dl class="bank">
            <dt>Banka</dt><dd>{{ $bank['bank_name'] ?: '—' }}</dd>
            <dt>Alıcı</dt><dd>{{ $bank['account_holder'] ?: '—' }} @if ($bank['account_holder'])<button type="button" class="copy" data-copy="{{ $bank['account_holder'] }}">Kopyala</button>@endif</dd>
            <dt>IBAN</dt><dd>{{ $ibanPretty }} <button type="button" class="copy" data-copy="{{ $bank['iban'] }}">Kopyala</button></dd>
        </dl>
        <p class="muted small" style="margin:10px 0 0">Havale açıklamasına işletme ve şube adınızı yazın: <b>{{ $branch->business->name }} – {{ $branch->name }}</b></p>
    @endif
</div>
