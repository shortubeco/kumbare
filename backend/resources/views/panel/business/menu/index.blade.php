@extends('layouts.panel')
@php use App\Support\Format; @endphp
@section('title', 'Menü')

@section('content')
    <div class="p-head">
        <div>
            <h1>Menü</h1>
            <p>Menü ve fiyatlar markanıza aittir; tüm şubelerin sayfasında aynı görünür. Geçici olarak biten ürünü silmek yerine "Satışa kapat" ile "Şu an mevcut değil" gösterebilirsiniz.</p>
        </div>
        @if ($categories->isNotEmpty())<a class="btn gold" href="{{ route('biz.menu.items.create') }}">Ürün ekle</a>@endif
    </div>

    <div class="card">
        <form class="filters" method="post" action="{{ route('biz.menu.categories.store') }}" style="margin:0">
            @csrf
            <div class="field" style="flex:1 1 260px">
                <label for="new_cat">Yeni kategori</label>
                <input id="new_cat" name="name" type="text" maxlength="100" required placeholder="Örn. Ana yemekler, Tatlılar, İçecekler">
            </div>
            <button class="btn primary" type="submit">Kategori ekle</button>
        </form>
    </div>

    @forelse ($categories as $c)
        <section class="card flush" id="kategori-{{ $c->id }}">
            <div class="card-head" style="padding-bottom:10px">
                <h2>{{ $c->name }} <span class="muted small">({{ $c->items->count() }} ürün)</span> @unless ($c->is_active)<span class="badge warn">Gizli</span>@endunless</h2>
                <div class="actions">
                    @unless ($loop->first)
                        <form method="post" action="{{ route('biz.menu.categories.move', $c->id) }}">@csrf<input type="hidden" name="yon" value="yukari"><button class="btn sm" type="submit" aria-label="{{ $c->name }} kategorisini yukarı taşı">↑</button></form>
                    @endunless
                    @unless ($loop->last)
                        <form method="post" action="{{ route('biz.menu.categories.move', $c->id) }}">@csrf<input type="hidden" name="yon" value="asagi"><button class="btn sm" type="submit" aria-label="{{ $c->name }} kategorisini aşağı taşı">↓</button></form>
                    @endunless
                    <details class="act">
                        <summary class="btn sm">Düzenle</summary>
                        <div class="pop">
                            <form method="post" action="{{ route('biz.menu.categories.update', $c->id) }}">
                                @csrf
                                <div class="field"><label for="cat-{{ $c->id }}">Kategori adı</label><input id="cat-{{ $c->id }}" name="name" type="text" maxlength="100" value="{{ $c->name }}" required></div>
                                <label class="check" style="margin-bottom:10px"><input type="checkbox" name="is_active" value="1" @checked($c->is_active)> Menüde göster</label>
                                <button class="btn sm primary" type="submit">Kaydet</button>
                            </form>
                            @if ($c->items->isEmpty())
                                <form method="post" action="{{ route('biz.menu.categories.delete', $c->id) }}" style="margin-top:10px">@csrf<button class="btn sm danger" type="submit">Kategoriyi sil</button></form>
                            @endif
                        </div>
                    </details>
                    <a class="btn sm gold" href="{{ route('biz.menu.items.create', ['kategori' => $c->id]) }}">Ürün ekle</a>
                </div>
            </div>
            @if ($c->items->isEmpty())
                <p class="empty">Bu kategoride ürün yok.</p>
            @else
                <div class="tbl-wrap">
                    <table class="tbl">
                        <tbody>
                        @foreach ($c->items as $i)
                            <tr @unless ($i->is_available) style="opacity:.65" @endunless>
                                <td style="width:70px">@if ($i->image_path)<img class="camp-img" src="{{ \App\Http\Resources\Support::fileUrl($i->image_path) }}" alt="">@endif</td>
                                <td><strong>{{ $i->name }}</strong>@unless ($i->is_available) <span class="badge warn">Şu an mevcut değil</span>@endunless
                                    @if ($i->description)<span class="sub">{{ \Illuminate\Support\Str::limit($i->description, 120) }}</span>@endif</td>
                                <td class="n money">{{ Format::money($i->price) }}</td>
                                <td class="nowrap">
                                    <div class="actions" style="justify-content:flex-end">
                                        @unless ($loop->first)
                                            <form method="post" action="{{ route('biz.menu.items.move', $i->id) }}">@csrf<input type="hidden" name="yon" value="yukari"><button class="btn sm" type="submit" aria-label="{{ $i->name }} yukarı">↑</button></form>
                                        @endunless
                                        @unless ($loop->last)
                                            <form method="post" action="{{ route('biz.menu.items.move', $i->id) }}">@csrf<input type="hidden" name="yon" value="asagi"><button class="btn sm" type="submit" aria-label="{{ $i->name }} aşağı">↓</button></form>
                                        @endunless
                                        <form method="post" action="{{ route('biz.menu.items.toggle', $i->id) }}">@csrf<button class="btn sm" type="submit">{{ $i->is_available ? 'Satışa kapat' : 'Satışa aç' }}</button></form>
                                        <a class="btn sm" href="{{ route('biz.menu.items.edit', $i->id) }}">Düzenle</a>
                                        <details class="act">
                                            <summary class="btn sm danger">Sil</summary>
                                            <div class="pop"><p>"{{ $i->name }}" menüden kaldırılsın mı?</p>
                                                <form method="post" action="{{ route('biz.menu.items.delete', $i->id) }}">@csrf<button class="btn sm danger solid" type="submit">Evet, kaldır</button></form>
                                            </div>
                                        </details>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @empty
        <div class="card"><p class="empty">Menünüz boş. Önce bir kategori ekleyin (örn. "Ana yemekler"), sonra ürünleri ekleyin.</p></div>
    @endforelse
@endsection
