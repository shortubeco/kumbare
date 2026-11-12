@extends('layouts.site')

@section('content')
<section class="hero">
    <div class="wrap">
        <div>
            <h1>Müşterileriniz size geri dönsün.</h1>
            <p class="hero-lead">Kumbare'de müşterileriniz her harcamada sizin belirlediğiniz oranda ödül kazanır. Ödül yalnızca sizin restoranınızda harcanır; yani kazanılan her lira, müşterinin tekrar gelmesi için bir sebep olur.</p>
            <div class="bant-actions">
                <a class="btn btn-coin" href="#basvuru">Ücretsiz başvurun</a>
                <a class="btn btn-ghost btn-on-dark" href="#paketler">Paketleri görün</a>
            </div>
        </div>
        <div>
            <ol class="adimlar adimlar-dark">
                <li><h3>Başvurun</h3><p>Formu doldurun; başvurunuzu inceleyip hesabınızı açalım.</p></li>
                <li><h3>Şubenizi ve oranınızı girin</h3><p>Menünüzü, çalışma saatlerinizi ve ödül oranınızı (%0–%100) panelden belirleyin.</p></li>
                <li><h3>Kasada müşteri numarasını girin</h3><p>Personeliniz numarayı girer, ödül müşterinin kumbarasına düşer.</p></li>
            </ol>
        </div>
    </div>
</section>

<section class="section">
    <div class="wrap">
        <div class="section-head"><h2>Neler dahil?</h2></div>
        <ul class="yerler ozellikler">
            <li>Şube bazında ödül oranı ve kampanyalar</li>
            <li>Menü ve fiyat yönetimi</li>
            <li>Çalışan hesapları ve yetkileri</li>
            <li>Müşteri ve işlem geçmişi</li>
            <li>Günlük, haftalık, aylık raporlar</li>
            <li>Yorumlara cevap verme</li>
            <li>Doğum günü ve yeni müşteri kampanyaları</li>
            <li>Herkese açık restoran sayfası</li>
        </ul>
    </div>
</section>

<section class="section section-alt" id="paketler">
    <div class="wrap">
        <div class="section-head">
            <div>
                <h2>Paketler</h2>
                <p>İki pakette de bütün özellikler aynıdır. Tek fark, bir ayda ödül verebileceğiniz farklı müşteri sayısıdır. Paket her şube için ayrı seçilir.</p>
            </div>
        </div>
        <div class="paketler">
            <div class="paket">
                <h3>Ücretsiz</h3>
                <span class="fiyat">0 TL</span>
                <ul>
                    <li>Ayda <span class="num">{{ $freeLimit }}</span> farklı müşteriye ödül</li>
                    <li>Bütün özellikler</li>
                    <li>Sayaç her ayın 1'inde sıfırlanır</li>
                    <li>Limit dolsa da müşterileriniz ödüllerini kullanabilir</li>
                </ul>
            </div>
            <div class="paket paket-pro">
                <h3>Profesyonel</h3>
                <span class="fiyat">
                    @if ($proMonthly)
                        {{ \App\Support\Format::money($proMonthly) }} <small>/ ay</small>
                    @else
                        Fiyat için bize ulaşın
                    @endif
                </span>
                <ul>
                    <li>Aylık müşteri sınırı yok</li>
                    <li>Bütün özellikler</li>
                    <li>Aylık veya yıllık ödeme
                        @if ($proYearly) (yıllık {{ \App\Support\Format::money($proYearly) }}) @endif
                    </li>
                    <li>Ödeme havale ile yapılır</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<section class="section" id="basvuru">
    <div class="wrap">
        <div class="section-head">
            <div>
                <h2>Başvuru formu</h2>
                <p>Başvurunuz onaylandığında bu e-posta ve şifreyle işletme paneline giriş yapacaksınız. Şubelerinizi panelden eklersiniz.</p>
            </div>
        </div>

        @if ($errors->any())
            <p class="uyari" role="alert">Formda düzeltilmesi gereken {{ $errors->count() }} alan var.</p>
        @endif

        <form class="form-grid" action="{{ route('web.business.apply') }}" method="post" novalidate>
            @csrf
            {{-- Bot tuzağı: tarayıcıların otomatik dolduracağı bir ad/etiket (ör. "website") kullanılmaz. --}}
            <div class="hp" aria-hidden="true"><label for="kb_bos">Bu alanı boş bırakın</label><input id="kb_bos" name="kb_bos" type="text" value="" tabindex="-1" autocomplete="one-time-code"></div>

            <fieldset>
                <legend>İşletme</legend>
                @include('site.partials.field', ['name' => 'business_name', 'label' => 'İşletme (marka) adı', 'autocomplete' => 'organization'])
                <div class="field">
                    <label for="category_id">Mutfak</label>
                    <select id="category_id" name="category_id" @error('category_id') aria-invalid="true" aria-describedby="category_id-hata" @enderror>
                        <option value="">Seçin</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}" @selected(old('category_id') == $cat->id)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id')<span class="field-error" id="category_id-hata">{{ $message }}</span>@enderror
                </div>
                @include('site.partials.field', ['name' => 'legal_name', 'label' => 'Ticari unvan'])
                @include('site.partials.field', ['name' => 'tax_office', 'label' => 'Vergi dairesi'])
                @include('site.partials.field', ['name' => 'tax_number', 'label' => 'Vergi / T.C. kimlik numarası', 'inputmode' => 'numeric'])
            </fieldset>

            <fieldset>
                <legend>Yetkili kişi</legend>
                @include('site.partials.field', ['name' => 'first_name', 'label' => 'Ad', 'autocomplete' => 'given-name'])
                @include('site.partials.field', ['name' => 'last_name', 'label' => 'Soyad', 'autocomplete' => 'family-name'])
                @include('site.partials.field', ['name' => 'email', 'label' => 'E-posta', 'type' => 'email', 'autocomplete' => 'email'])
                @include('site.partials.field', ['name' => 'phone', 'label' => 'Telefon', 'type' => 'tel', 'autocomplete' => 'tel'])
                @include('site.partials.field', ['name' => 'password', 'label' => 'Şifre (en az 10 karakter)', 'type' => 'password', 'autocomplete' => 'new-password', 'noOld' => true])
                @include('site.partials.field', ['name' => 'password_confirmation', 'label' => 'Şifre tekrar', 'type' => 'password', 'autocomplete' => 'new-password', 'noOld' => true])
            </fieldset>

            <div class="full">
                <label class="check"><input type="checkbox" name="kvkk" value="1" @checked(old('kvkk'))> <span><a href="{{ route('web.kvkk') }}" target="_blank">KVKK aydınlatma metnini</a> okudum, kişisel verilerimin başvurunun değerlendirilmesi için işlenmesini kabul ediyorum.</span></label>
                @error('kvkk')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="full"><button class="btn btn-coin" type="submit">Başvuruyu gönder</button></div>
        </form>
    </div>
</section>
@endsection
