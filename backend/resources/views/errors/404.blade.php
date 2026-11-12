@extends('layouts.site', ['seo' => ['title' => 'Sayfa bulunamadı | Kumbare', 'robots' => 'noindex,follow']])

@section('content')
<section class="section">
    <div class="wrap bos">
        <h1>Bu sayfa bulunamadı</h1>
        <p>Restoran kaldırılmış ya da adres değişmiş olabilir. Aradığın restoranı listeden bulabilirsin.</p>
        <form class="hero-search" action="{{ route('web.branches') }}" method="get" role="search">
            <label class="visually-hidden" for="q404">Restoran ara</label>
            <input id="q404" name="q" type="search" placeholder="Restoran adı ya da yemek">
            <button class="btn" type="submit">Ara</button>
        </form>
    </div>
</section>
@endsection
