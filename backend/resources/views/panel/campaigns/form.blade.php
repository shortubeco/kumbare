@extends('layouts.panel')
@php
    use App\Support\Format;
    $c = $campaign;
    $type = old('type', $c?->type ?? 'rate_override');
    $days = old('weekdays', $c ? $c->weekdayList() : []);
    $rate = old('reward_rate', $c?->reward_rate !== null ? rtrim(rtrim(number_format((float) $c->reward_rate, 2, ',', ''), '0'), ',') : '');
    $startsOn = old('starts_on', $c ? $c->starts_at->timezone(config('app.timezone'))->toDateString() : now()->toDateString());
    $endsOn = old('ends_on', $c ? $c->ends_at->copy()->subSecond()->timezone(config('app.timezone'))->toDateString() : now()->addDays(30)->toDateString());
@endphp
@section('title', $c ? 'Kampanyayı düzenle' : 'Yeni kampanya')

@section('content')
    <div class="p-head">
        <div>
            <div class="crumb"><a href="{{ route('panel.campaigns') }}">← Kampanyalar</a></div>
            <h1>{{ $c ? 'Kampanyayı düzenle' : 'Yeni kampanya' }}</h1>
            <p>Şube ödül oranınız: <b>{{ Format::rate($branch->reward_rate) }}</b>. Oran kampanyası, işlem ekranında bu oranın yerine seçilebilir.</p>
        </div>
    </div>

    <form method="post" action="{{ $c ? route('panel.campaigns.update', $c->id) : route('panel.campaigns.store') }}" enctype="multipart/form-data" novalidate>
        @csrf
        <div class="grid two" style="align-items:start">
            <div class="card">
                <fieldset>
                    <legend>Kampanya türü</legend>
                    <div class="choice">
                        <label><input type="radio" name="type" value="rate_override" @checked($type === 'rate_override')>
                            <span><b>Oran kampanyası</b><small>Örn. hafta içi %15 ödül</small></span></label>
                        <label><input type="radio" name="type" value="announcement" @checked($type === 'announcement')>
                            <span><b>Duyuru</b><small>Örn. yeni menü, etkinlik</small></span></label>
                    </div>
                </fieldset>
                <div class="field">
                    <label for="title">Başlık</label>
                    <input id="title" name="title" type="text" maxlength="150" value="{{ old('title', $c?->title) }}" required placeholder="Örn. Hafta içi %15 ödül" @error('title') aria-invalid="true" @enderror>
                    @error('title')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field" id="oran-alani" @if ($type !== 'rate_override') hidden @endif>
                    <label for="reward_rate">Kampanya ödül oranı (%)</label>
                    <input id="reward_rate" name="reward_rate" type="text" inputmode="decimal" value="{{ $rate }}" style="max-width:160px" @error('reward_rate') aria-invalid="true" @enderror>
                    @error('reward_rate')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="description">Açıklama <span class="muted">(isteğe bağlı)</span></label>
                    <textarea id="description" name="description" maxlength="2000">{{ old('description', $c?->description) }}</textarea>
                </div>
                <div class="field">
                    <label for="terms">Koşullar <span class="muted">(isteğe bağlı)</span></label>
                    <textarea id="terms" name="terms" maxlength="2000" placeholder="Örn. Paket serviste geçerli değildir.">{{ old('terms', $c?->terms) }}</textarea>
                </div>
            </div>

            <div class="card">
                <div class="form-grid">
                    <div class="field">
                        <label for="starts_on">Başlangıç</label>
                        <input id="starts_on" name="starts_on" type="date" value="{{ $startsOn }}" required @error('starts_on') aria-invalid="true" @enderror>
                        @error('starts_on')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="ends_on">Bitiş (dahil)</label>
                        <input id="ends_on" name="ends_on" type="date" value="{{ $endsOn }}" required @error('ends_on') aria-invalid="true" @enderror>
                        @error('ends_on')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <fieldset>
                    <legend>Geçerli günler</legend>
                    <div class="weekdays">
                        @foreach (Format::DAYS as $d => $name)
                            <label><input type="checkbox" name="weekdays[]" value="{{ $d }}" @checked(in_array($d, array_map('intval', $days), true))>{{ $name }}</label>
                        @endforeach
                    </div>
                    <span class="hint muted small">Hiçbiri seçilmezse her gün geçerlidir.</span>
                </fieldset>
                <fieldset style="margin-top:12px">
                    <legend>Kimler için</legend>
                    <label class="check"><input type="checkbox" name="new_customers_only" value="1" @checked(old('new_customers_only', $c?->new_customers_only))> Yalnızca yeni müşteriler (bu şubede ilk kez ödül kazanacaklar)</label>
                    <label class="check" style="margin-top:6px"><input type="checkbox" name="birthday_only" value="1" @checked(old('birthday_only', $c?->birthday_only))> Yalnızca doğum gününde (29 Şubat doğumlular 28 Şubat'ta)</label>
                </fieldset>
                <div class="field" style="margin-top:14px">
                    <label for="image">Görsel <span class="muted">(isteğe bağlı)</span></label>
                    @if ($c?->image_path)
                        <img class="camp-img" style="width:160px;height:120px" src="{{ \App\Http\Resources\Support::fileUrl($c->image_path) }}" alt="">
                        <label class="check"><input type="checkbox" name="remove_image" value="1"> Görseli kaldır</label>
                    @endif
                    <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp">
                    <span class="hint">JPG, PNG veya WEBP; en fazla 6 MB.</span>
                    @error('image')<span class="field-error">{{ $message }}</span>@enderror
                </div>
            </div>
        </div>

        <div class="actions">
            <button class="btn gold" type="submit" name="publish" value="active">{{ $c && $c->status === 'active' ? 'Kaydet' : 'Yayımla' }}</button>
            <button class="btn" type="submit" name="publish" value="draft">{{ $c && $c->status === 'active' ? 'Yayından al (taslak yap)' : 'Taslak olarak kaydet' }}</button>
            <a class="btn" href="{{ route('panel.campaigns') }}">Vazgeç</a>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    (function () {
        var box = document.getElementById('oran-alani');
        document.querySelectorAll('[name=type]').forEach(function (r) {
            r.addEventListener('change', function () { box.hidden = document.querySelector('[name=type]:checked').value !== 'rate_override'; });
        });
    })();
</script>
@endpush
