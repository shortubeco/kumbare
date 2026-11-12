@extends('layouts.site')

@section('content')
<section class="section">
    <div class="wrap">
        <div class="uyg-head">
            <h1>Kumbare uygulaması</h1>
            <p>Restoranlardaki ödül bakiyelerini, kampanyaları ve kasadan gelen onay isteklerini telefonundan takip et. Üye olunca sana bir müşteri numarası verilir; ödemede bu numarayı söylemen yeterli.</p>
        </div>

        <div class="uyg-grid">
            <article class="uyg-kart" id="iphone">
                <h2>iPhone</h2>
                <p>Kumbare iPhone'da Safari üzerinden uygulama gibi çalışır; App Store'dan indirmen gerekmez.</p>
                <ol class="uyg-adim">
                    <li>Bu sayfayı iPhone'unda <b>Safari</b> ile aç.</li>
                    <li>Aşağıdaki <b>Uygulamayı aç</b> düğmesine dokun.</li>
                    <li>Alt çubuktaki <b>Paylaş</b> düğmesine (yukarı ok olan kare) dokun.</li>
                    <li><b>Ana Ekrana Ekle</b>'yi seç, sonra <b>Ekle</b>'ye dokun.</li>
                </ol>
                <p class="uyg-not">Kumbare simgesi ana ekranına gelir; oradan açtığında tam ekran uygulama olarak çalışır.</p>
                <div class="actions"><a class="btn btn-coin" href="{{ url('/uygulama/ana-sayfa') }}">Uygulamayı aç</a></div>
            </article>

            <article class="uyg-kart" id="android">
                <h2>Android</h2>
                <p>İndirilebilir Android uygulaması (APK) çok yakında bu sayfada olacak.</p>
                <p>O zamana kadar Kumbare'yi Chrome'da açıp ana ekrana ekleyebilirsin:</p>
                <ol class="uyg-adim">
                    <li>Aşağıdaki <b>Uygulamayı aç</b> düğmesine dokun.</li>
                    <li>Chrome'un sağ üstündeki <b>⋮</b> menüsüne dokun.</li>
                    <li><b>Ana ekrana ekle</b>'yi (veya <b>Uygulamayı yükle</b>) seç.</li>
                </ol>
                <div class="actions"><a class="btn btn-ghost" href="{{ url('/uygulama/ana-sayfa') }}">Uygulamayı aç</a></div>
            </article>
        </div>

        <p class="uyg-bilgisayar">Bilgisayardasın? Telefonundan <b>{{ preg_replace('#^https?://#', '', url('/mobil-uygulama')) }}</b> adresini açman yeterli.</p>
    </div>
</section>
@endsection
