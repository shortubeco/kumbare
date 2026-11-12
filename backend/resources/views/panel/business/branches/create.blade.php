@extends('layouts.panel')
@section('title', 'Şube ekle')

@section('content')
    <div class="p-head">
        <div>
            <div class="crumb"><a href="{{ route('biz.branches') }}">← Şubeler</a></div>
            <h1>Şube ekle</h1>
            <p>Yeni şube Ücretsiz paketle ve %0 ödül oranıyla açılır. Sonraki adımda şube yöneticisini tanımlarsınız; oranı ve çalışanları şube yöneticisi belirler.</p>
        </div>
    </div>

    <form class="card" method="post" action="{{ route('biz.branches.store') }}" novalidate style="max-width:680px"
          data-districts="{{ route('biz.districts', ['city' => '__ID__']) }}" id="sube-formu">
        @csrf
        <div class="form-grid">
            <div class="field">
                <label for="city_id">İl</label>
                <select id="city_id" name="city_id" required @error('city_id') aria-invalid="true" @enderror>
                    <option value="">Seçin</option>
                    @foreach ($cities as $c)
                        <option value="{{ $c->id }}" @selected((int) old('city_id') === $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
                @error('city_id')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="field">
                <label for="district_id">İlçe</label>
                <select id="district_id" name="district_id" required data-old="{{ old('district_id') }}" @error('district_id') aria-invalid="true" @enderror>
                    <option value="">Önce il seçin</option>
                </select>
                @error('district_id')<span class="field-error">{{ $message }}</span>@enderror
            </div>
        </div>
        <div class="field">
            <label for="name">Şube adı</label>
            <input id="name" name="name" type="text" maxlength="150" value="{{ old('name') }}" required placeholder="Örn. Kadıköy veya Moda" @error('name') aria-invalid="true" @enderror>
            <span class="hint">Sitede "{{ $business->name }} – Şube adı" olarak görünür. Genellikle semt veya ilçe adı yazılır.</span>
            @error('name')<span class="field-error">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="address">Açık adres</label>
            <textarea id="address" name="address" maxlength="500" required @error('address') aria-invalid="true" @enderror>{{ old('address') }}</textarea>
            @error('address')<span class="field-error">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="phone">Telefon <span class="muted">(isteğe bağlı)</span></label>
            <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" @error('phone') aria-invalid="true" @enderror>
            @error('phone')<span class="field-error">{{ $message }}</span>@enderror
        </div>
        <button class="btn gold" type="submit">Şubeyi ekle</button>
    </form>
@endsection

@push('scripts')
<script>
    (function () {
        var f = document.getElementById('sube-formu'), city = document.getElementById('city_id'), dist = document.getElementById('district_id');
        var name = document.getElementById('name');
        function load() {
            dist.textContent = '';
            if (!city.value) { var o = document.createElement('option'); o.value = ''; o.textContent = 'Önce il seçin'; dist.appendChild(o); return; }
            var l = document.createElement('option'); l.value = ''; l.textContent = 'Yükleniyor…'; dist.appendChild(l);
            fetch(f.dataset.districts.replace('__ID__', city.value), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (list) {
                    dist.textContent = '';
                    var first = document.createElement('option'); first.value = ''; first.textContent = 'Seçin'; dist.appendChild(first);
                    list.forEach(function (d) {
                        var o = document.createElement('option'); o.value = d.id; o.textContent = d.name;
                        if (String(d.id) === dist.dataset.old) o.selected = true;
                        dist.appendChild(o);
                    });
                });
        }
        city.addEventListener('change', function () { dist.dataset.old = ''; load(); });
        dist.addEventListener('change', function () {
            if (!name.value && dist.selectedIndex > 0) name.value = dist.options[dist.selectedIndex].textContent;
        });
        if (city.value) load();
    })();
</script>
@endpush
