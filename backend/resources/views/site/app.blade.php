@extends('layouts.site')

@section('content')
<section class="section">
    <div class="wrap">
        <div class="uyg-head">
            <p class="bant-etiket">Müşteriler için · ücretsiz</p>
            <h1>Kumbare uygulaması</h1>
            <p>Restoranlarda ödül biriktirmek için uygulamayı kur ve ücretsiz üye ol. Üye olunca sana bir müşteri numarası verilir; ödemede bu numarayı söylemen yeterli. Ödül bakiyelerini, kampanyaları ve kasadan gelen onay isteklerini telefonundan takip edersin.</p>
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

            @php($apk = \App\Support\AndroidRelease::current())
            <article class="uyg-kart" id="android">
                <h2>Android</h2>
                @if ($apk)
                    <p>Kumbare Android uygulamasını buradan indirip kurabilirsin; Google Play'den indirmen gerekmez.</p>
                    <ol class="uyg-adim">
                        <li>Aşağıdaki <b>Android uygulamasını indir</b> düğmesine dokun.</li>
                        <li>İndirme bitince bildirimden ya da <b>İndirilenler</b>'den <b>kumbare.apk</b> dosyasını aç.</li>
                        <li>Telefon izin isterse <b>Ayarlar</b>'a gidip <b>Bu kaynağa izin ver</b>'i aç, geri dön.</li>
                        <li><b>Yükle</b>'ye dokun. Kumbare simgesi ana ekranına gelir.</li>
                    </ol>
                    <p class="uyg-not">Sürüm {{ $apk['version_name'] }} · {{ number_format($apk['size_mb'], 1, ',', '.') }} MB. Yeni sürüm çıktığında uygulama sana haber verir.</p>
                    <div class="actions"><a class="btn btn-coin" href="{{ $apk['url'] }}" download>Android uygulamasını indir</a></div>
                @else
                    <p>İndirilebilir Android uygulaması (APK) çok yakında bu sayfada olacak.</p>
                    <p>O zamana kadar Kumbare'yi Chrome'da açıp ana ekrana ekleyebilirsin:</p>
                    <ol class="uyg-adim">
                        <li>Aşağıdaki <b>Uygulamayı aç</b> düğmesine dokun.</li>
                        <li>Chrome'un sağ üstündeki <b>⋮</b> menüsüne dokun.</li>
                        <li><b>Ana ekrana ekle</b>'yi (veya <b>Uygulamayı yükle</b>) seç.</li>
                    </ol>
                    <div class="actions"><a class="btn btn-ghost" href="{{ url('/uygulama/ana-sayfa') }}">Uygulamayı aç</a></div>
                @endif
            </article>
        </div>

        <p class="uyg-bilgisayar">Bilgisayardasın? Telefonundan <b>{{ preg_replace('#^https?://#', '', url('/mobil-uygulama')) }}</b> adresini açman yeterli.</p>
        <p class="uyg-bilgisayar">Restoran sahibi misiniz? Bu uygulama müşteriler içindir. Restoranınızı Kumbare'ye eklemek için <a href="{{ route('web.business') }}">İşletmeler için</a> sayfasına bakın.</p>
    </div>
</section>
@endsection
