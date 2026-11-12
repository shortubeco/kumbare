# Kumbare – Teknik Tasarım: Mobil Uygulama

> Kaynak: `iş planı.txt`. Diğer parçalar: `teknik-tasarim-api.md` (backend ve API), `teknik-tasarim-db.md` (veritabanı), `teknik-tasarim-web.md` (web sitesi ve paneller).
> Bu belge kod içermez.

---

## 1. Teknoloji

| Konu | Seçim | Not |
|---|---|---|
| Çatı | React Native + Expo | iOS ve Android tek kod tabanı |
| Derleme | **Android: imzalı APK** (EAS Build veya yerel); **iPhone: PWA** (aynı koddan web dışa aktarımı) | **Karar (12 Kasım 2026):** mağaza yok, Expo Go yok. Dosyalar sitede "Android" ve "iOS" bağlantıları altında. Apple Türkiye'de siteden kurulum izni vermediği için iPhone'da "Ana ekrana ekle" ile web uygulaması. App Store/TestFlight sonraya bırakıldı |
| Navigasyon | Expo Router | Dosya tabanlı rotalar |
| Sunucu verisi | TanStack Query | Önbellek, yenileme, sayfalama |
| Token saklama | `expo-secure-store` | |
| Push bildirim | **Yok** | Bütün veri uygulama açılışında ve Yenile butonlarıyla çekilir; bildirim izni istenmez |
| Konum | `expo-location` | Yalnızca "yakındaki restoranlar" için; izin isteğe bağlıdır |
| Pano | `expo-clipboard` | Müşteri numarasını kopyalama |
| Görsel | `expo-image` | Önbellekli görsel yükleme |
| Dil / para birimi | Türkçe, TL | Tutarlar kuruşlarıyla gösterilir: 1.234,50 TL |

API sözleşmesi için bkz. `teknik-tasarim-api.md` bölüm 7.

---

## 2. Ekran Haritası

```
(auth) — giriş yapılmamışsa
  giris              e-posta, şifre, [Giriş], [Kayıt ol], "Şifremi unuttum"
  kayit              ad, soyad, telefon, il, ilçe, doğum tarihi, e-posta, şifre, şifre tekrar, KVKK onayı
  sifre-sifirla      e-posta → sıfırlama bağlantısı gönderilir

(app) — giriş yapılmışsa
  üst çubuk:  [🔔 Bildirimler]  [🏷 Kampanyalar]
  alt sekmeler:
    ana-sayfa
    kesfet
    kumbaram
    profil

  sube/[id]                  şube detayı
  sube/[id]/degerlendir      puan + yorum yaz/düzenle
  kumbara/[id]               kumbara detayı
  bildirimler                onay bekleyen istekler
  kampanyalar                ilçedeki kampanyalar
  profil/duzenle, profil/yorumlarim, profil/favoriler
```

---

## 3. Ekranlar

### 3.1. Açılış ve Giriş

- Uygulama açıldığında güvenli depoda token varsa doğrudan ana sayfa açılır. Token yoksa **giriş ekranı** gösterilir.
- Giriş ekranında e-posta ve şifre alanları, Giriş butonu, **Kayıt ol** butonu ve "Şifremi unuttum" bağlantısı bulunur.
- Kayıt ekranında ad, soyad, telefon, il, ilçe, doğum tarihi, e-posta, şifre ve şifre tekrar alanları ile KVKK onay kutusu yer alır. İl seçilince ilçe listesi o ile göre dolar. Doğum tarihi tarih seçiciyle girilir ve altında "Doğum günü kampanyalarından yararlanmak için. Sonradan değiştirilemez." notu yer alır. E-posta doğrulaması yoktur. Kayıt başarılı olunca kullanıcı giriş yapmış sayılır ve müşteri numarasını gösteren bir karşılama ekranı açılır.
- Sunucudan 401 yanıtı gelirse token silinir ve giriş ekranına dönülür.

### 3.2. Ana Sayfa

Sırasıyla şu bölümler yer alır:
- Arama kutusu
- Öne çıkan/sponsorlu restoranlar ("Sponsorlu" etiketiyle)
- Yakındaki restoranlar (konum izni varsa; yoksa kayıtlı ilçedeki restoranlar)
- En yüksek ödül oranları
- Kampanyalar
- Favori restoranlar
- Yeni eklenenler

Her bölüm yatay kaydırılan kartlardan oluşur. Kart içeriği web sitesindeki kartla aynıdır.

### 3.3. Keşfet

- Liste görünümü, sonsuz kaydırma ile.
- **Filtreler** (alttan açılan panel): il/ilçe, kategori, minimum ödül oranı, minimum puan, kampanyalı olanlar.
- **Sıralama seçenekleri:**
  - En yüksek ödül oranı
  - En düşük ödül oranı
  - En popüler
  - En yeni
  - En yüksek puan (en az 10 değerlendirme)
  - En çok değerlendirilen
  - En yakın (konum izni varsa)

### 3.4. Şube Detayı

Web sitesindeki detay sayfasının karşılığıdır: başlık (puan, oran rozeti), foto galeri, bilgiler ve çalışma saatleri, harita/yol tarifi (cihazın harita uygulamasını açar), ödül oranı ve koşullar, kampanyalar, menü (kategori sekmeleri), diğer şubeler ve değerlendirmeler.

- Favori butonu bulunur.
- Müşteri bu şubede ödül kazanmışsa **"Değerlendir"** butonu görünür. Daha önce değerlendirmişse buton "Değerlendirmemi düzenle" olur.
- Değerlendirme ekranında 1'den 10'a kadar sayı butonları ve yorum alanı bulunur. **Yıldız kullanılmaz.**

### 3.5. Kumbaram

- Liste: her kumbara için "Restoran A – Kadıköy" ve bakiye (örn. 250,00 TL). Bakiyeye göre sıralanır.
- **Kumbara detayı** şunları gösterir:
  - Mevcut bakiye (büyük puntoyla)
  - Toplam kazanılan ve toplam kullanılan ödül
  - Ödül kullanım koşulları
  - "Restoran sayfasına git" bağlantısı (şube detayını açar)
  - İşlem geçmişi: tarih, tür (Kazanıldı / Kullanıldı / Düzeltme), tutar, kazanımlarda harcama, oran ve kampanya adı
- Boş durumda şu mesaj gösterilir: "Henüz kumbaran yok. Restoranda müşteri numaranı söyleyerek ödül kazanmaya başla."

### 3.6. Bildirimler (onay istekleri)

Sunucuya `GET /approvals?status=pending` isteği şu durumlarda gönderilir:
1. **Uygulama açıldığında** (ve arka plandan ön plana geldiğinde). Bekleyen istek sayısı Bildirimler butonunda rozet olarak gösterilir.
2. Bildirimler sayfasının **en üstündeki "Yenile" butonuna** basıldığında. İstek sürerken buton yükleniyor durumuna geçer ve yeni gelen istekler listeye eklenir.

Bildirimler butonuna basmak sunucuya istek **göndermez**; sayfa, son sorgudan elde edilen listeyi gösterir.

Saat başı arka plan kontrolü ve push bildirim **kullanılmaz.**
- İki tür kart vardır:

```
┌ Ödül Kullanımı ─────────────────────┐
│ Restoran A – Kadıköy                │
│ Kullanılacak: 300,00 TL             │
│ Mevcut bakiye: 450,00 TL → 150,00 TL│
│ İsteyen: Mehmet (personel)          │
│        [Reddet]   [Onayla]          │
└─────────────────────────────────────┘
┌ Düzeltme ───────────────────────────┐
│ Restoran A – Kadıköy                │
│ İşlem: 12.11.2026 · +1.000,00 TL    │
│ Düzeltme: −900,00 TL                │
│ Açıklama: Tutar hatalı girildi      │
│        [Reddet]   [Onayla]          │
└─────────────────────────────────────┘
```

- Onay ve ret işlemleri için önce bir onay penceresi açılır ("300,00 TL ödülünüz kullanılacak. Onaylıyor musunuz?").
- İşlem sonucu ekranda gösterilir. Bakiye yetersizse sunucunun döndürdüğü hata mesajı görüntülenir.

### 3.7. Kampanyalar

- Kayıtlı ilçedeki şubelerin aktif kampanyaları listelenir: restoran ve şube adı, kampanya başlığı, tarih aralığı ve oran (varsa).
- Sorgulama Bildirimler ekranıyla aynı şekilde çalışır. `GET /campaigns` isteği yalnızca şu durumlarda gönderilir:
  1. **Uygulama açıldığında** (ve arka plandan ön plana geldiğinde).
  2. Kampanyalar sayfasının **en üstündeki "Yenile" butonuna** basıldığında.
- Kampanyalar butonuna basmak sunucuya istek göndermez; sayfa son sorguda gelen listeyi gösterir.
- İlçe profilden değiştirildiğinde liste bir kez yeniden çekilir.
- Kampanyaya dokunulunca şube detayı açılır.
- Ekranın üstünde "Kampanyalar: Fatih, İstanbul" bilgisi ve "İlçeyi değiştir" bağlantısı (profil düzenlemeye gider) bulunur.

### 3.8. Profil

- En üstte **"Müşteri Numaram: 125"** yazar. Rakamlar büyük puntoyla gösterilir ve yanında **Kopyala** butonu bulunur.
- Ad, soyad, telefon, e-posta, il ve ilçe düzenlenebilir. Doğum tarihi gösterilir ama düzenlenemez ("Hatalıysa destek ile iletişime geçin").
- Yorumlarda görünen ad tercihi: "Ahmet Y." (varsayılan) veya tam ad.
- Yorumlarım, Favorilerim, Şifre değiştir, Çıkış ve Hesabı sil seçenekleri yer alır.

---

## 4. Veri Yenileme Modeli

Uygulama push bildirim ve periyodik arka plan isteği kullanmaz. Sunucudan veri yalnızca şu durumlarda çekilir:

| Veri | Ne zaman çekilir |
|---|---|
| Onay bekleyen istekler (Bildirimler) | Uygulama açılışı / ön plana gelme, Bildirimler sayfasındaki Yenile butonu |
| Kampanyalar | Uygulama açılışı / ön plana gelme, Kampanyalar sayfasındaki Yenile butonu, ilçe değişikliği |
| Diğer ekranlar (keşif, kumbara, şube detayı) | Ekran açıldığında |

İlerleyen sürümlerde gerekirse push bildirim (ör. FCM) sonradan eklenebilir; mevcut API buna engel değildir.

---

## 5. Durum, Hata ve Çevrimdışı Davranış

- Liste ve detay verileri TanStack Query ile önbelleğe alınır. Çevrimdışıyken son görülen veriler gösterilir ve "Bağlantı yok" uyarısı çıkar.
- Onaylama ve reddetme işlemleri çevrimdışıyken yapılamaz; butonlar devre dışı kalır.
- Hata mesajları Türkçedir ve sunucunun `message` alanından gösterilir.
- Para tutarları sunucudan metin olarak gelir ve istemcide hesaplama yapılmaz. Böylece kayan nokta hatası oluşmaz.

---

## 6. Yayınlama

| Kalem | Ücret | Not |
|---|---|---|
| Apple Developer Program | Yıllık 99 USD | App Store yayını için |
| Google Play Console | Bir kerelik 25 USD | |
| EAS Build | Ücretsiz plan yeterli olabilir | Aylık derleme sınırı vardır; derleme yerel olarak da yapılabilir |

- Uygulama kimliği (bundle id / package name) örneği: `com.kumbare.app`.
- Ortamlar: `development` (yerel API), `preview` (test sunucusu), `production`. API adresi derleme profiline göre belirlenir.
- Mağaza için gereken içerikler: gizlilik politikası URL'si, KVKK metni, hesap silme bağlantısı (her iki mağaza da zorunlu tutuyor), ekran görüntüleri.


## 7. Uygulama durumu (13 Kasım 2026)

Dağıtım kararı (bkz. iş planı + CLAUDE.md): mağaza yok. **iPhone:** web uygulaması (PWA) — `https://kumbare.trthabercanli.com/uygulama/ana-sayfa`, Safari'de "Ana Ekrana Ekle". **Android:** siteden indirilen imzalı APK (6. adım, henüz yapılmadı); o zamana kadar Chrome'dan ana ekrana ekleme. Kurulum anlatımı sitede `/mobil-uygulama`.

| Adım | İçerik | Durum |
|---|---|---|
| 1 | Giriş, kayıt (il/ilçe, gün/ay/yıl doğum tarihi, KVKK onayı), şifre sıfırlama isteği, "Hoş geldiniz" (müşteri numarası), 4 sekme | Yerelde denendi |
| 2 | Kumbaram (toplam + şube kumbaraları), kumbara ayrıntısı + sayfalı işlem geçmişi, Bildirimler (onayla/reddet, Yenile), zilde rozet, ana sayfada uyarı | Yerelde uçtan uca denendi (kasa API'si → uygulamada onay/ret, yetersiz bakiye) |
| 3 | Ana sayfa blokları, Keşfet (arama, il/ilçe, mutfak, sıralama, kampanyalı, ödül veren), şube sayfası (Bilgiler / Menü / Değerlendirmeler, favori, kumbaram, kampanyalar), Kampanyalar, Favorilerim, değerlendirme yazma ve şikâyet | Yerelde denendi |
| 4 | Profil menüsü, bilgileri düzenleme (doğum tarihi ve müşteri no değişmez), şifre değiştirme, Değerlendirmelerim, hesap silme, KVKK metni, sürüm | Yerelde denendi |
| 5 | PWA: `/uygulama` altında yayın, manifest + iPhone meta, simgeler, "Ana ekrana ekle" ipucu, site sayfası `/mobil-uygulama` | Paket 21 hazır, canlı test bekliyor |
| 6 | Android APK + uygulama içi "yeni sürüm var" kontrolü + sitede indirme bağlantısı | Sırada |

Uygulamada **olmayanlar / sonraya kalanlar:** konumla "yakınımdakiler" (şimdilik kayıtlı ilçe kullanılıyor; telefondan konum izni gerekir), push bildirim (iş planı gereği yok), service worker / çevrimdışı çalışma.

Teknik notlar: Expo SDK 58, expo-router (`src/app`), React Query, TypeScript. Web derlemesi `experiments.baseUrl: /uygulama`, `web.output: single`; giriş dosyası sunucuda `app.html`, alt adresleri Laravel `Web\\AppController` karşılar. Ayrıntılar CLAUDE.md "Mobil uygulama" maddesinde.
