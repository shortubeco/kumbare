@extends('layouts.panel')
@section('title', 'İşletme bilgileri')

@section('content')
    <div class="p-head">
        <div>
            <h1>İşletme bilgileri</h1>
            <p>Marka adı, logo, açıklama ve mutfak türü tüm şubelerde ortaktır; sitede ve uygulamada görünür.</p>
        </div>
    </div>

    <div class="grid two" style="align-items:start">
        <form class="card" method="post" action="{{ route('biz.profile.update') }}" enctype="multipart/form-data" novalidate>
            @csrf
            <div class="field">
                <label for="name">İşletme (marka) adı</label>
                <input id="name" name="name" type="text" maxlength="150" value="{{ old('name', $business->name) }}" required @error('name') aria-invalid="true" @enderror>
                <span class="hint">Ad değişse de sitedeki adres (bağlantı) değişmez.</span>
                @error('name')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="field">
                <label for="category_id">Mutfak türü</label>
                <select id="category_id" name="category_id" required>
                    @foreach ($categories as $c)
                        <option value="{{ $c->id }}" @selected((int) old('category_id', $business->category_id) === $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
                @error('category_id')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="field">
                <label for="description">Açıklama</label>
                <textarea id="description" name="description" maxlength="2000" rows="5">{{ old('description', $business->description) }}</textarea>
            </div>
            <div class="field">
                <label for="logo">Logo</label>
                @if ($business->logo_path)
                    <img src="{{ \App\Http\Resources\Support::fileUrl($business->logo_path) }}" alt="{{ $business->name }} logosu" style="width:96px;height:96px;object-fit:contain;border:1px solid var(--cizgi);border-radius:var(--r-md);background:#fff">
                    <label class="check"><input type="checkbox" name="remove_logo" value="1"> Logoyu kaldır</label>
                @endif
                <input id="logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp">
                <span class="hint">Kare logo önerilir; JPG, PNG veya WEBP, en fazla 4 MB.</span>
                @error('logo')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <button class="btn primary" type="submit">Kaydet</button>
        </form>

        <div class="card">
            <h2>Resmî bilgiler</h2>
            <dl class="kv">
                <dt>Ticari unvan</dt><dd>{{ $business->legal_name ?: '—' }}</dd>
                <dt>Vergi dairesi</dt><dd>{{ $business->tax_office ?: '—' }}</dd>
                <dt>Vergi numarası</dt><dd>{{ $business->tax_number ?: '—' }}</dd>
                <dt>Kumbare'ye katılış</dt><dd>{{ \App\Support\Labels::date($business->approved_at ?? $business->created_at) }}</dd>
            </dl>
            <p class="muted small" style="margin-top:12px">Resmî bilgilerde değişiklik için bizimle iletişime geçin.</p>
        </div>
    </div>
@endsection
