@extends('layouts.admin')
@php use App\Support\Format; @endphp
@section('title', 'Kategoriler')

@section('content')
    <div class="p-head">
        <div>
            <h1>Restoran kategorileri</h1>
            <p>İşletmelerin seçtiği mutfak türleri. Kullanılan kategori silinmez; pasif yapılırsa yeni başvurularda seçilemez.</p>
        </div>
    </div>

    <div class="card">
        <h2>Yeni kategori</h2>
        <form method="post" action="{{ route('admin.categories.store') }}" class="filters">
            @csrf
            <div class="field" style="flex:1 1 220px">
                <label for="new_name">Ad</label>
                <input id="new_name" type="text" name="name" required maxlength="100" value="{{ old('name') }}" placeholder="Örn. Balık">
            </div>
            <div class="field" style="width:120px">
                <label for="new_sort">Sıra</label>
                <input id="new_sort" type="number" name="sort_order" min="0" max="10000" value="{{ old('sort_order', 0) }}">
            </div>
            <label class="check" style="margin-bottom:10px"><input type="checkbox" name="is_active" value="1" checked> Aktif</label>
            <button class="btn primary" type="submit">Ekle</button>
        </form>
    </div>

    <div class="card flush">
        <h2>Kategoriler ({{ $categories->count() }})</h2>
        @foreach ($categories as $c)
            <form method="post" action="{{ route('admin.categories.update', $c) }}" id="cat-{{ $c->id }}" hidden>@csrf</form>
        @endforeach
        <div class="tbl-wrap">
            <table class="tbl">
                <thead><tr><th>Ad</th><th>Adres (slug)</th><th class="n">Sıra</th><th>Aktif</th><th class="n">İşletme</th><th></th></tr></thead>
                <tbody>
                @foreach ($categories as $c)
                    <tr>
                        <td><input form="cat-{{ $c->id }}" type="text" name="name" value="{{ $c->name }}" required maxlength="100" aria-label="{{ $c->name }} adı"></td>
                        <td><input form="cat-{{ $c->id }}" type="text" name="slug" value="{{ $c->slug }}" maxlength="120" aria-label="{{ $c->name }} adresi">
                            <span class="sub">/kategori/{{ $c->slug }}</span></td>
                        <td class="n" style="width:100px"><input form="cat-{{ $c->id }}" type="number" name="sort_order" value="{{ $c->sort_order }}" min="0" max="10000" aria-label="{{ $c->name }} sırası"></td>
                        <td><label class="check"><input form="cat-{{ $c->id }}" type="checkbox" name="is_active" value="1" @checked($c->is_active)> Aktif</label></td>
                        <td class="n">{{ Format::number($c->businesses_count) }}</td>
                        <td><button form="cat-{{ $c->id }}" class="btn sm" type="submit">Kaydet</button></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p class="muted small" style="padding:0 20px 16px">Not: Adres (slug) değişirse sitedeki kategori bağlantısı ve mutfak fotoğrafı eşleşmesi değişir; gerekmedikçe değiştirmeyin.</p>
    </div>
@endsection
