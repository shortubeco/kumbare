@extends('layouts.panel')
@php $i = $item; @endphp
@section('title', $i ? 'Ürünü düzenle' : 'Ürün ekle')

@section('content')
    <div class="p-head">
        <div>
            <div class="crumb"><a href="{{ route('biz.menu') }}">← Menü</a></div>
            <h1>{{ $i ? $i->name : 'Ürün ekle' }}</h1>
            <p>Fiyat değişikliği tüm şubelerde hemen geçerli olur.</p>
        </div>
    </div>

    @if ($categories->isEmpty())
        <div class="card"><p>Önce <a href="{{ route('biz.menu') }}">bir kategori ekleyin</a>.</p></div>
    @else
    <form class="card" method="post" action="{{ $i ? route('biz.menu.items.update', $i->id) : route('biz.menu.items.store') }}" enctype="multipart/form-data" novalidate style="max-width:680px">
        @csrf
        <div class="form-grid">
            <div class="field">
                <label for="menu_category_id">Kategori</label>
                <select id="menu_category_id" name="menu_category_id" required>
                    @foreach ($categories as $c)
                        <option value="{{ $c->id }}" @selected((int) old('menu_category_id', $selected) === $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
                @error('menu_category_id')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="field">
                <label for="price">Fiyat (TL)</label>
                <input id="price" name="price" type="text" inputmode="decimal" value="{{ old('price', $i ? number_format((float) $i->price, 2, ',', '.') : '') }}" required placeholder="Örn. 185,00" @error('price') aria-invalid="true" @enderror>
                @error('price')<span class="field-error">{{ $message }}</span>@enderror
            </div>
        </div>
        <div class="field">
            <label for="name">Ürün adı</label>
            <input id="name" name="name" type="text" maxlength="150" value="{{ old('name', $i?->name) }}" required @error('name') aria-invalid="true" @enderror>
            @error('name')<span class="field-error">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="description">Açıklama <span class="muted">(isteğe bağlı)</span></label>
            <textarea id="description" name="description" maxlength="1000" placeholder="Örn. Közde pişmiş patlıcan, yoğurt, tereyağı">{{ old('description', $i?->description) }}</textarea>
        </div>
        <div class="field">
            <label for="image">Görsel <span class="muted">(isteğe bağlı)</span></label>
            @if ($i?->image_path)
                <img class="camp-img" style="width:160px;height:120px" src="{{ \App\Http\Resources\Support::fileUrl($i->image_path) }}" alt="">
                <label class="check"><input type="checkbox" name="remove_image" value="1"> Görseli kaldır</label>
            @endif
            <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp">
            <span class="hint">JPG, PNG veya WEBP; en fazla 6 MB. Görsel küçültülüp sıkıştırılır.</span>
            @error('image')<span class="field-error">{{ $message }}</span>@enderror
        </div>
        {{-- İşaretsiz kutu gönderilmez; gizli "0" ile "satışta değil" bilgisi taşınır --}}
        <input type="hidden" name="is_available" value="0">
        <label class="check" style="margin-bottom:14px"><input type="checkbox" name="is_available" value="1" @checked(old('is_available', $i ? $i->is_available : true))> Satışta (işaretsizse sitede "Şu an mevcut değil" yazar)</label>
        <div class="actions">
            <button class="btn gold" type="submit">{{ $i ? 'Kaydet' : 'Ürünü ekle' }}</button>
            <a class="btn" href="{{ route('biz.menu') }}">Vazgeç</a>
        </div>
    </form>
    @endif
@endsection
