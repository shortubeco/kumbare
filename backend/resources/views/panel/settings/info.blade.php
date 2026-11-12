@extends('layouts.panel')
@php use App\Support\Format; @endphp
@section('title', 'Şube bilgileri')

@section('content')
    <div class="p-head">
        <div>
            <h1>Şube bilgileri</h1>
            <p>Bu bilgiler web sitesinde ve uygulamada şube sayfanızda görünür. Şube adı ve mutfak türünü işletme yöneticisi değiştirir.</p>
        </div>
        <a class="btn" href="{{ route('web.branch', [$branch->business->slug, $branch->slug]) }}" target="_blank" rel="noopener">Sitede gör ↗</a>
    </div>

    <form method="post" action="{{ route('panel.info.update') }}" novalidate>
        @csrf
        <div class="grid two" style="align-items:start">
            <div class="card">
                <h2>İletişim ve adres</h2>
                <dl class="kv" style="margin-bottom:14px">
                    <dt>Şube</dt><dd>{{ $branch->business->name }} – {{ $branch->name }}</dd>
                    <dt>İl / ilçe</dt><dd>{{ $branch->district?->name }}, {{ $branch->city?->name }}</dd>
                </dl>
                <div class="field">
                    <label for="address">Açık adres</label>
                    <textarea id="address" name="address" maxlength="500" required @error('address') aria-invalid="true" @enderror>{{ old('address', $branch->address) }}</textarea>
                    @error('address')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="form-grid">
                    <div class="field">
                        <label for="phone">Telefon</label>
                        <input id="phone" name="phone" type="tel" value="{{ old('phone', $branch->phone) }}" placeholder="02161234567" @error('phone') aria-invalid="true" @enderror>
                        @error('phone')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="email">E-posta</label>
                        <input id="email" name="email" type="email" value="{{ old('email', $branch->email) }}" @error('email') aria-invalid="true" @enderror>
                        @error('email')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <fieldset>
                    <legend>Haritadaki konum</legend>
                    <div class="form-grid">
                        <div class="field">
                            <label for="latitude">Enlem</label>
                            <input id="latitude" name="latitude" type="text" inputmode="decimal" value="{{ old('latitude', $branch->latitude) }}" placeholder="41.0082" @error('latitude') aria-invalid="true" @enderror>
                            @error('latitude')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="longitude">Boylam</label>
                            <input id="longitude" name="longitude" type="text" inputmode="decimal" value="{{ old('longitude', $branch->longitude) }}" placeholder="28.9784" @error('longitude') aria-invalid="true" @enderror>
                            @error('longitude')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="actions">
                        <button type="button" class="btn sm" id="konumum">Şu an bulunduğum konumu kullan</button>
                        <span class="hint muted small" id="konum-durum">Şubedeyken basarsanız konum otomatik dolar. Ya da Google Haritalar'da şubeye sağ tıklayıp çıkan sayıları yazın.</span>
                    </div>
                </fieldset>
                <div class="field" style="margin-top:14px">
                    <label for="description">Şube açıklaması <span class="muted">(isteğe bağlı)</span></label>
                    <textarea id="description" name="description" maxlength="2000">{{ old('description', $branch->description) }}</textarea>
                </div>
            </div>

            <div class="card">
                <h2>Çalışma saatleri</h2>
                <p class="muted small">Saat girilmeyen gün sitede "belirtilmemiş" görünür. Gece yarısını geçen kapanışlar için kapanışı ertesi günün saati olarak yazın (örn. 01:00).</p>
                <div class="tbl-wrap">
                    <table class="tbl hours">
                        <thead><tr><th>Gün</th><th>Açılış</th><th>Kapanış</th><th>Kapalı</th></tr></thead>
                        <tbody>
                        @foreach ($hours as $d => $h)
                            @php
                                $closed = old("hours.$d.closed", $h?->is_closed ? '1' : null);
                                $opens = old("hours.$d.opens", $h?->opens_at ? substr($h->opens_at, 0, 5) : '');
                                $closes = old("hours.$d.closes", $h?->closes_at ? substr($h->closes_at, 0, 5) : '');
                            @endphp
                            <tr>
                                <td><b>{{ Format::DAYS[$d] }}</b></td>
                                <td><input type="time" name="hours[{{ $d }}][opens]" value="{{ $opens }}" aria-label="{{ Format::DAYS[$d] }} açılış"></td>
                                <td><input type="time" name="hours[{{ $d }}][closes]" value="{{ $closes }}" aria-label="{{ Format::DAYS[$d] }} kapanış"></td>
                                <td><label class="check"><input type="checkbox" name="hours[{{ $d }}][closed]" value="1" @checked($closed)> Kapalı</label></td>
                            </tr>
                            @error("hours.$d.opens")<tr><td colspan="4"><span class="field-error">{{ $message }}</span></td></tr>@enderror
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <button type="button" class="btn sm" id="saat-kopyala" style="margin-top:10px">Pazartesi saatlerini tüm açık günlere uygula</button>
            </div>
        </div>
        <button class="btn primary" type="submit">Bilgileri kaydet</button>
    </form>

    {{-- Fotoğraflar (ayrı form: dosya yükleme) --}}
    <div class="card" style="margin-top:22px">
        <div class="card-head">
            <h2>Fotoğraflar ({{ $branch->photos->count() }} / {{ $maxPhotos }})</h2>
            <span class="muted small">İlk fotoğraf kapak olarak kullanılır. Fotoğraflar küçültülüp sıkıştırılır.</span>
        </div>
        @if ($branch->photos->isNotEmpty())
            <div class="photo-grid">
                @foreach ($branch->photos as $p)
                    <figure>
                        <img src="{{ \App\Http\Resources\Support::fileUrl($p->path) }}" alt="{{ $branch->name }} fotoğrafı {{ $loop->iteration }}" loading="lazy">
                        <figcaption class="actions">
                            @if ($loop->first)
                                <span class="badge ok">Kapak</span>
                            @else
                                <form method="post" action="{{ route('panel.photos.cover', $p->id) }}">@csrf<button class="btn sm" type="submit">Kapak yap</button></form>
                            @endif
                            <form method="post" action="{{ route('panel.photos.delete', $p->id) }}">@csrf<button class="btn sm danger" type="submit">Sil</button></form>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        @else
            <p class="muted">Henüz fotoğraf yok. Fotoğraf eklemezseniz sitede mutfak türünüze uygun temsilî görsel kullanılır.</p>
        @endif

        @if ($branch->photos->count() < $maxPhotos)
            <form method="post" action="{{ route('panel.photos.upload') }}" enctype="multipart/form-data" class="filters" style="margin-top:14px">
                @csrf
                <div class="field" style="flex:1 1 280px">
                    <label for="photos">Fotoğraf ekle</label>
                    <input id="photos" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple required>
                    <span class="hint">JPG, PNG veya WEBP; her biri en fazla 6 MB. Birden fazla seçebilirsiniz.</span>
                    @error('photos')<span class="field-error">{{ $message }}</span>@enderror
                    @error('photos.*')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <button class="btn gold" type="submit">Yükle</button>
            </form>
        @endif
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        var btn = document.getElementById('konumum'), st = document.getElementById('konum-durum');
        if (btn) btn.addEventListener('click', function () {
            if (!navigator.geolocation) { st.textContent = 'Tarayıcınız konum vermiyor; sayıları elle yazın.'; return; }
            st.textContent = 'Konum alınıyor…';
            navigator.geolocation.getCurrentPosition(function (p) {
                document.getElementById('latitude').value = p.coords.latitude.toFixed(7);
                document.getElementById('longitude').value = p.coords.longitude.toFixed(7);
                st.textContent = 'Konum dolduruldu (±' + Math.round(p.coords.accuracy) + ' m). Kaydetmeyi unutmayın.';
            }, function () { st.textContent = 'Konum alınamadı (izin verilmedi). Sayıları elle yazabilirsiniz.'; }, { enableHighAccuracy: true, timeout: 10000 });
        });
        var copy = document.getElementById('saat-kopyala');
        if (copy) copy.addEventListener('click', function () {
            var o = document.querySelector('[name="hours[1][opens]"]').value, c = document.querySelector('[name="hours[1][closes]"]').value;
            for (var d = 2; d <= 7; d++) {
                if (document.querySelector('[name="hours[' + d + '][closed]"]').checked) continue;
                document.querySelector('[name="hours[' + d + '][opens]"]').value = o;
                document.querySelector('[name="hours[' + d + '][closes]"]').value = c;
            }
        });
    })();
</script>
@endpush
