# Kumbare – Teknik Tasarım: Web Sitesi ve Paneller

> Kaynak: `iş planı.txt`. Diğer parçalar: `teknik-tasarim-api.md` (backend ve API), `teknik-tasarim-db.md` (veritabanı), `teknik-tasarim-mobile.md` (mobil uygulama).
> Bu belge kod içermez.

---

## 1. Teknoloji

| Alan | Teknoloji | Gerekçe |
|---|---|---|
| Herkese açık web sitesi | Laravel Blade (sunucu tarafı render) | SEO gereksinimi; JavaScript olmadan da içerik görünür |
| İşletme / şube / çalışan paneli | Blade + Livewire | Teknik bilgisi olmayan kullanıcılar için özel tasarım; ayrı bir SPA build'i gerekmez |
| Platform yönetim paneli | Filament | Liste, filtre ve form ekranlarını hızlı üretir; Laravel ile aynı kod tabanında çalışır |
| Stil | Tailwind CSS (Vite ile derlenip deploy paketine eklenir) | |
| Harita | Şube detayında harita gösterimi ve "Yol tarifi al" bağlantısı | |

Tüm arayüz Türkçedir ve tutarlar TL olarak, kuruşlarıyla gösterilir (örn. 1.234,50 TL).

---

## 2. Herkese Açık Web Sitesi

### 2.1. Rotalar

| Yol | Sayfa |
|---|---|
| `/` | Ana sayfa |
| `/restoranlar` | Listeleme (filtre ve sıralama query string ile) |
| `/restoranlar/{il}/{ilce}` | Konum sayfası (SEO için ayrı URL) |
| `/kategori/{slug}` | Kategori sayfası |
| `/restoran/{isletme-slug}/{sube-slug}` | Şube detay sayfası |
| `/isletmeler-icin` | İşletme tanıtımı ve başvuru formu |
| `/isletmeler-icin/basvuru` | İşletme başvurusu (işletme yöneticisi hesabı + işletme bilgileri) |
| `/sitemap.xml`, `/robots.txt` | |

### 2.2. Ana Sayfa

Ana sayfada şu bloklar bulunur:
- Tanıtım alanı
- Arama kutusu
- Sponsorlu/öne çıkan restoranlar (her zaman "Sponsorlu" etiketiyle)
- Yakındaki restoranlar (tarayıcı konum izni verilirse; verilmezse bu blok gizlenir)
- En yüksek ödül oranı sunanlar
- Yeni eklenenler
- Popüler kategoriler
- Kampanyalı restoranlar
- Mobil uygulama indirme alanı
- İşletmelere yönelik kayıt alanı

### 2.3. Restoran Kartı

Her şube ayrı bir kart olarak gösterilir:

```
┌────────────────────────────────┐
│ [görsel/logo]       [Sponsorlu]│
│ Restoran A – Kadıköy           │
│ Kebap · Kadıköy, İstanbul      │
│ 8.7 / 10 — 245 değerlendirme   │
│ %10 ödül kazan                 │
└────────────────────────────────┘
```

- Değerlendirmesi olmayan şubede puan yerine "Henüz değerlendirme yok" yazar.
- Oranı %0 olan şubede oran yerine "Ödül yok" yazar.
- **Yıldız simgesi hiçbir yerde kullanılmaz**; puan her zaman sayı olarak gösterilir.

### 2.4. Listeleme Filtreleri ve Sıralama

- **Filtreler:** Kategori, il/ilçe, minimum ödül oranı, kampanyalı olanlar, yeni eklenenler, minimum puan.
- **Sıralama seçenekleri:**
  - En yüksek ödül oranı
  - En düşük ödül oranı
  - En popüler (son 30 gündeki işlem sayısı)
  - En yeni
  - En yüksek puan (en az 10 değerlendirme şartı)
  - En çok değerlendirilen
- Sponsorlu şubeler listenin üstünde ayrı bir bölümde, "Sponsorlu" etiketiyle gösterilir.
- Sayfalama sunucu tarafında yapılır ve URL'de taşınır.

### 2.5. Şube Detay Sayfası

Bölümler:
1. **Başlık:** Logo, restoran ve şube adı, puan ("8.7 / 10 — 245 değerlendirme"), kategori, ödül oranı rozeti
2. **Foto galeri**
3. **Bilgiler:** Açıklama, adres, harita/ulaşım, telefon, e-posta, çalışma saatleri
4. **Ödül:** Kazanma oranı, kullanım koşulları
5. **Kampanyalar:** Şubenin aktif kampanyaları
6. **Menü:** Kategori sekmeleri, ürün adı, açıklama, görsel, fiyat. Satışa kapalı ürünler "Şu an mevcut değil" olarak gösterilir.
7. **Diğer şubeler:** Aynı markanın diğer şubelerine bağlantılar
8. **Değerlendirmeler:** Ortalama puan, değerlendirme sayısı, puan dağılımı, yorumlar (görünen ad, puan, tarih, restoranın cevabı). Yorumlar en yeni, en eski veya puana göre sıralanabilir. Web sitesinde yorum yazılamaz; yorum yazmak için mobil uygulamaya yönlendirilir.

### 2.6. SEO

- Her sayfada benzersiz `title`, `meta description` ve canonical URL bulunur.
- Şube detay sayfalarında schema.org `Restaurant` işaretlemesi kullanılır (adres, telefon, çalışma saatleri, `aggregateRating`, `menu`). `aggregateRating` yalnızca değerlendirmesi olan şubelerde eklenir.
- Open Graph etiketleri (paylaşım görseli) eklenir.
- Günlük olarak üretilen `sitemap.xml` yayınlanır.
- Görseller lazy-load ile yüklenir ve WebP formatında sunulur.

---

## 3. Panel (`/panel`) — İşletme, Şube, Çalışan

Giriş sayfası `/panel/giris` tek bir sayfadır; kullanıcının rolüne göre menü değişir. Panel mobil tarayıcıda da kullanılabilecek şekilde duyarlı tasarlanır.

### 3.1. İşletme Yöneticisi Menüsü

| Ekran | İçerik |
|---|---|
| Genel Bakış | Tüm şubelerin toplamı ve şube bazında karşılaştırma: müşteri sayıları, bu ayın tekil müşterisi, kazandırılan/kullanılan ödül, kullanılabilir bakiye, işlem sayıları, şube paketleri |
| İşletme Bilgileri | Ad, logo, açıklama, kategori |
| Şubeler | Şube listesi; ekleme, silme, aktif/pasif yapma, şube detayını görüntüleme |
| Şube Yöneticileri | Her şube için yönetici hesabı oluşturma ve pasifleştirme |
| Menü | Kategoriler ve ürünler (sürükle-bırak sıralama, görsel yükleme, fiyat, geçici satışa kapatma) |
| Raporlar | Şube bazında ve işletme toplamı (bkz. 3.4) |
| Abonelikler | Şubelerin paket durumlarını görüntüleme (yalnızca okuma) |

### 3.2. Şube Yöneticisi Menüsü

| Ekran | İçerik |
|---|---|
| Genel Bakış | Toplam müşteri, bu ayın tekil müşterisi / limit (ilerleme çubuğu, %80 ve %90 uyarıları), kazandırılan/kullanılan ödül, kullanılabilir bakiye, günlük/haftalık/aylık işlem sayıları, en aktif müşteriler, paket bilgisi |
| **İşlem Ekranı** | Kazandır / Kullandır (bkz. 3.3) |
| Müşteriler | Şubede işlem yapmış müşteriler, bakiye, toplamlar, işlem geçmişi |
| Düzeltmeler | Düzeltme isteği oluşturma ve isteklerin durumu (bekliyor / onaylandı / reddedildi) |
| Ödül Oranı | %0–%100 arası oran, kullanım koşulları metni, oran değişiklik geçmişi |
| Kampanyalar | Oluşturma ve düzenleme: tip (duyuru / oran kampanyası), oran, tarih aralığı, haftanın günleri, yalnızca yeni müşteriler, **yalnızca doğum günü olanlar**, görsel, koşullar |
| Çalışanlar | Çalışan ekleme, pasifleştirme, yetki işaretleme |
| Şube Bilgileri | Adres, konum (haritadan işaretleme), iletişim, çalışma saatleri, fotoğraflar |
| Değerlendirmeler | Ortalama, sayı, puan dağılımı, yorumlar, cevap yazma (silme/düzenleme yetkisi yok) |
| Raporlar | Şube raporları |
| Paket ve Ödeme | Mevcut paket, Profesyonel'in avantajı (sınırsız aylık müşteri), aylık/yıllık fiyat, havale bilgileri, havale bildirimi formu, geçmiş bildirimler |
| Sponsorluk | Ay seçerek sponsorluk başvurusu ve havale bildirimi |
| Bildirimler | Panel bildirimleri (eşikler, limit, abonelik, onay sonuçları) |

### 3.3. İşlem Ekranı (en kritik ekran)

Tablet ve telefon tarayıcısında hızlı kullanım için tasarlanır: büyük giriş alanları, büyük butonlar ve az adım.

**Adım 1 – Müşteri bul**
```
Müşteri Numarası: [ 125      ]  [Bul]
→ Ahmet Yılmaz  🎂 Bugün doğum günü  ·  Bu şubedeki bakiye: 250,00 TL
   [ Ödül Kazandır ]   [ Ödül Kullandır ]
```
"Bugün doğum günü" etiketi yalnızca müşterinin doğum günündeyse görünür. Doğum tarihinin kendisi personele gösterilmez.

**Ödül Kazandır**
```
Ödenen tutar:  [ 1.000,00 ] TL
Kampanya:      ( ) Kampanya yok – %10
               ( ) Hafta içi %15
               ( ) Yeni müşteriye %20       ← yalnızca bu müşteri için uygun olanlar
               ( ) Doğum günü %25           ← yalnızca müşterinin doğum günündeyse
────────────── Özet ──────────────
Ahmet Yılmaz (125)
Harcama 1.000,00 TL × %15 (Hafta içi) = 150,00 TL ödül
Yeni bakiye: 400,00 TL
                      [İptal] [Onayla]
```
- Limit dolmuşsa ve müşteri bu ay henüz sayılmamışsa uyarı gösterilir ve Onayla butonu devre dışı kalır. Ekranda "Profesyonel pakete geç" bağlantısı yer alır.
- Mükerrer tıklamaya karşı buton tek gönderim yapar (idempotency anahtarıyla).

**Ödül Kullandır**
```
Kullanılacak tutar: [ 300,00 ] TL   (bakiye 250,00 TL'yi aşamaz)
────────────── Özet ──────────────
[İsteği Gönder]
→ "Müşteri onayı bekleniyor…"  (birkaç saniyede bir durum sorgulanır)
→ ✔ Onaylandı: 300,00 TL indirimi uygulayın. Kalan bakiye: 0,00 TL
   veya ✖ Reddedildi
[İsteği geri çek]
```
- Onay gelmeden "İndirimi uygulayın" mesajı **gösterilmez**.

### 3.4. Raporlar

- Günlük müşteri sayısı, aylık tekil müşteri sayısı, toplam müşteri sayısı
- Yeni ve tekrar gelen müşteriler
- Kazandırılan ve kullandırılan toplam ödül, kullanılabilir ödül bakiyesi
- Kampanya performansı (kampanya bazında işlem sayısı ve verilen ödül)
- En aktif müşteriler
- Günlük, haftalık ve aylık işlem istatistikleri
- İşletme yöneticisi için şube karşılaştırması

Tarih aralığı seçilebilir. Grafikler basit çizgi ve çubuk grafiklerdir. Tablolar CSV olarak dışa aktarılabilir.

### 3.5. Çalışan Menüsü

İşlem ekranı her zaman görünür. Müşteriler ve Raporlar menüleri yalnızca ilgili yetki verilmişse görünür.

---

## 4. Platform Yönetim Paneli (`/yonetim`, Filament)

| Ekran | İçerik |
|---|---|
| İstatistikler | Toplam/aktif işletme ve şube sayısı, toplam/aktif müşteri, toplam işlem, kazandırılan/kullanılan ödül, Free/Pro şube sayıları, aylık abonelik ve sponsorluk gelirleri |
| İşletme Başvuruları | Bekleyen başvurular, onay/ret (gerekçeli) |
| İşletmeler ve Şubeler | Liste ve detay, askıya alma/kapatma, abonelik ve aylık müşteri sayıları |
| Müşteriler | Liste, arama (numara/ad/e-posta/telefon), pasifleştirme, hatalı doğum tarihini düzeltme (denetim kaydına yazılır) |
| Kategoriler | Restoran kategorileri |
| Paketler | Free/Pro açıklamaları, Pro aylık ve yıllık fiyat |
| Havale Bildirimleri | Bekleyen bildirimler; onaylanınca abonelik veya sponsorluk otomatik oluşturulur |
| Abonelikler | Şube bazında başlangıç/bitiş tarihleri ve durum |
| Sponsorluklar | Aylık sponsorluk listesi (sınır yok) |
| Kampanya Moderasyonu | Uygunsuz kampanyaları kaldırma |
| Yorum Şikâyetleri | İnceleme, yorumu kaldırma |
| Ayarlar | Ücretsiz paket limiti, uyarı eşikleri (%), abonelik uyarı gün sayısı, banka/IBAN bilgileri, sponsorluk fiyatı |
| Denetim Kayıtları | `audit_logs` arama; olağandışı müşteri numarası sorgu raporu |

Platform yöneticisi müşteri bakiyelerini değiştiremez ve düzeltme yapamaz.

---

## 5. Tasarım İlkeleri

- Ödül oranları ve puanlar her kartta belirgin olmalıdır (renkli oran rozeti, büyük puan rakamı).
- Sponsorlu içerik her zaman açıkça etiketlenir.
- Panel dili sade olmalıdır; teknik terim kullanılmaz ("işlem", "kumbara", "ödül").
- Formlarda hata mesajları Türkçe ve alanın yanında gösterilir.
- Mobil uyumlu tasarım uygulanır. Erişilebilirlik için yeterli kontrast, klavye ile kullanım ve form etiketleri sağlanır.

---

## 6. Uygulanan durum (12 Kasım 2026)

Herkese açık web sitesi yazıldı ve https://kumbare.trthabercanli.com adresinde yayında. Bu belgedeki plandan farklar:

- **Rotalar:** `/restoranlar`, `/restoranlar/{il}`, `/restoranlar/{il}/{ilce}`, `/kategori/{mutfak}`, `/restoran/{isletme}/{sube}`, `/isletmeler-icin` (+ başvuru), `/sifre-sifirla`, `/panel/sifre-sifirla`, `/kvkk-aydinlatma-metni`, `/sitemap.xml`, `/robots.txt`. Filtre parametreleri Türkçe: `q, il, ilce, mutfak, min_odul, min_puan, kampanya, yeni, siralama (populer|odul|odul-artan|puan|yorum|yeni|yakin), lat, lng, sayfa`.
- **Alan adı:** `WEB_DOMAIN` tanımlıyken site yalnızca o alan adında açılır; API alan adında kök JSON döner ve robots `Disallow: /`.
- **Tasarım:** "adisyon + kumbara" konsepti, fotoğraflar Unsplash (temsilî, `public/img/KAYNAKLAR.txt`), restoranlar için renkli "tabela". Stil düz CSS (`public/css/site.css`), Tailwind/Vite kullanılmadı.
- **Ana sayfa listeleri:** en yüksek puanlılar (≥10 değerlendirme), en çok değerlendirilenler, en yüksek ödül, mutfağa göre en iyiler (ağırlıklı puan: m=5, C=7.5), kampanyası olanlar, bu ay en popülerler, yeni katılanlar.
- **Grafikler:** Genel bakışta sunucuda çizilen SVG sütun grafikleri (`admin/partials/column-chart.blade.php`; üzerine gelince değer, altında tablo). Grafik renkleri marka ailesinden doğrulanmış tonlar: yeşil `#00897b`, koyu sarı `#b98600` (marka renkleri `#0e3b3a`/`#f2b705` grafikte kontrast ve renk körlüğü kontrolünü geçmiyor).
- **Panel teknolojisi (karar, 12 Kasım 2026):** Livewire/Filament/Tailwind **kullanılmaz** (bölüm 1'deki tablo bu konuda geçersiz); paneller de web sitesi gibi sade Blade + düz CSS/JS ile yazılır (`public/css/panel.css`, `public/js/panel.js`, `public/js/panel-islem.js`; düzenler `layouts/admin.blade.php`, `layouts/panel.blade.php`, `layouts/auth.blade.php`).
- **Yapım sırası (kullanıcı kararı):** önce platform yönetimi, sonra şube paneli (paket/ödeme ve havale öne alındı, ardından işlem ekranı ve genel bakış), sonra işletme yöneticisi paneli.
- **Ortak giriş:** `/panel/giris` (oturum tabanlı, `web` guard), `/panel/sifremi-unuttum`, `/panel/cikis`. Platform yöneticisi `/yonetim`'e; şube yöneticisi ve çalışan `/panel/islem`'e (işlem ekranı); işletme yöneticisi (paneli yazılana kadar) ve kapalı şube/işletme kullanıcıları `/panel` bilgi sayfasına yönlenir.
- **Platform yönetimi (`/yonetim`) yazıldı:** genel bakış (istatistik + son 6 ay gelir), başvurular (onay/gerekçeli ret, e-posta), işletmeler ve şubeler (askıya alma/kapatma, şube aktif/pasif, paket ve aylık sayaç), müşteriler (arama, pasifleştirme, denetimli doğum tarihi düzeltme), havale bildirimleri (onayda abonelik/sponsorluk otomatik; erken yenilemede yeni dönem mevcut bitişten başlar), abonelikler (iptal), sponsorluklar (ay bazında, iptal), kampanya moderasyonu, yorum şikâyetleri (kaldır / şikâyeti kapat), kategoriler, paketler ve ayarlar, denetim kayıtları + olağandışı numara sorgu raporu. İş kuralları `app/Domain/Platform/`.
- Platform genel bakışında iki grafik grubu var: müşteri/işlem (günlük işlem, aylık ödül, yeni müşteri, gelir) ve işletme (yeni işletme/şube, ay sonu Pro şube sayısı, en çok işlem yapan 10 şube, mutfağa göre şubeler — son ikisi yatay çubuk `admin/partials/bar-list.blade.php`). Rakam kutuları sabit sütunlu (3 / 5 / 3×2).

### 6.1. Şube paneli (`layouts/panel.blade.php`) — yazılanlar

| Ekran | Yol | Kim | Not |
|---|---|---|---|
| Genel bakış | `/panel/genel-bakis` | Şube yöneticisi | Bu ayın müşterisi / limit (%80, %90, dolu uyarıları), yeni / tekrar gelen (aylık sayaçla aynı ölçüt: ödül kazanma), paket ve kalan gün, onay bekleyen istek sayısı, işlem sayıları (bugün / 7 gün / ay), ödül toplamları, 2 grafik, en aktif 10 müşteri (son 30 gün) |
| **İşlem ekranı** | `/panel/islem` | Yönetici + çalışan (yetkiye göre düğmeler) | Bölüm 3.3'teki akış. Arka plan istekleri `/panel/islem/...` altında, personel API'sinin `TransactionController` metotlarıyla (oturumla). Kazandırmada idempotency anahtarı tutar/kampanya değişince yenilenir. Kullanımda istek sonrası 3 sn (2 dk sonra 10 sn) aralıkla durum sorgulanır, 30 dk sonra durur; onay gelmeden indirim mesajı gösterilmez |
| Müşteriler | `/panel/musteriler`, `/panel/musteriler/{no}` | Yönetici + `view_customers` yetkili çalışan | Şubede işlem yapmış müşteriler (no, ad, işlem sayısı, kazanılan, kullanılan, bakiye, son işlem); numara/ad araması, 5 sıralama. Ayrıntıda bakiye özeti, onay bekleyen istekler, işlem geçmişi (personel, kampanya, düzeltme durumları); yöneticide her kazanım/kullanım satırında "Düzelt". Telefon, e-posta, doğum tarihi gösterilmez |
| Düzeltmeler | `/panel/duzeltmeler`, `/panel/duzeltmeler/yeni?islem={id}` | Şube yöneticisi | Durum sekmeleri (onay bekleyen / onaylanan / reddedilen / geri çekilen); bekleyeni geri çekme. Yeni istek: yön (bakiyeye ekle / bakiyeden düş) + tutar + gerekçe (müşteri görür); kazanımda kazanılandan fazlası geri alınamaz, kullanımda kullanılandan fazlası iade edilemez; işlem başına tek bekleyen istek; idempotency anahtarı formda |
| Paket ve ödeme | `/panel/paket` | Şube yöneticisi | Aylık sayaç, Ücretsiz / Pro karşılaştırması ve fiyatlar, banka bilgileri (kopyala), havale bildirimleri geçmişi |
| Sponsorluk | `/panel/sponsorluk` | Şube yöneticisi | Bu ay + sonraki 5 ay; her ay "Sponsorlu / Bildirim onay bekliyor / Havale bildir" |
| Havale bildirimi | `/panel/havale-bildirimi` | Şube yöneticisi | Amaç (paket aylık/yıllık veya sponsorluk ayı), tutar (beklenen fiyat otomatik), havale tarihi (≤ bugün, ≥ 60 gün önce), gönderen, isteğe bağlı dekont (PDF/JPG/PNG/WEBP ≤ 4 MB, özel depoda). Onay bekleyen paket bildirimi varken yenisi ve aynı ay için ikinci sponsorluk bildirimi engellenir. İş kuralı `Domain/Billing/PaymentSubmissionService` |

| Ödül oranı | `/panel/odul-orani` | Şube yöneticisi | %0–100 (iki ondalık), kullanım koşulları metni, canlı örnek hesap, oran değişiklik geçmişi (`reward_rate_history`). Yeni oran yalnızca sonraki işlemlere uygulanır |
| Kampanyalar | `/panel/kampanyalar` (+ `yeni`, `{id}/duzenle`, `{id}/bitir`) | Şube yöneticisi | Sekmeler: yayında / başlayacak / taslak / sona eren; kullanım sayısı ve verilen ödül. Form: tür (oran / duyuru), başlık, oran, açıklama, koşullar, başlangıç–bitiş günü (bitiş dahil, en fazla 1 yıl), geçerli günler, yalnızca yeni müşteri, yalnızca doğum günü, görsel; "Yayımla" / "Taslak". Sona eren kampanya düzenlenemez |
| Çalışanlar | `/panel/calisanlar` | Şube yöneticisi | Ekle (ad, soyad, e-posta = giriş adı, telefon, yöneticinin belirlediği şifre ≥ 10, yetkiler), düzenle (yetki/şifre), pasifleştir/aktif et; son giriş ve 30 günlük işlem sayısı. Silme yok |
| Şube bilgileri | `/panel/sube-bilgileri` | Şube yöneticisi | Açık adres, telefon, e-posta, açıklama, enlem/boylam ("bulunduğum konumu kullan"), 7 günlük çalışma saatleri (kapalı işareti, gece yarısını geçen kapanış), fotoğraflar (en fazla 10, ilk = kapak, kapak yap / sil). Şube adı, il/ilçe ve mutfak işletme yöneticisindedir |
| Değerlendirmeler | `/panel/degerlendirmeler` | Şube yöneticisi | Ortalama, sayı, 1–10 dağılımı; filtre (tümü / cevap bekleyen / 1–5 puan) ve sıralama; görünen ad "Ad S."; cevap yaz/düzenle (tek cevap); yorumu platforma şikâyet et (`review_reports`, reporter_type panel_user). Menüde cevap bekleyen sayısı |
| Bildirimler | `/panel/bildirimler` | Yönetici + çalışan | `panel_notifications` Türkçe başlık/açıklama/bağlantıyla (limit eşikleri, havale onay/ret, abonelik bitişi/iptali, sponsorluk iptali, kampanya kaldırma, şube durumu, müşteri onay/ret); açılınca okundu sayılır; menüde okunmamış sayısı |

| Raporlar | `/panel/raporlar` | Yönetici + `view_reports` yetkili çalışan | Bölüm 3.4. Tarih aralığı: hazır (son 7/30/90 gün, bu ay, geçen ay, bu yıl) veya özel (≤ 366 gün). Döküm: ≤ 45 gün günlük, ≤ 200 gün haftalık (Pazartesi), üstü aylık. Özet: farklı müşteri (yeni / tekrar gelen), işlem (kazanım / kullanım / düzeltme), ödüllü harcama ve ortalama hesap, kazandırılan / kullanılan ödül, bugünkü kullanılabilir bakiye. Grafikler: müşteriler (yeni + tekrar üst üste), ödüller. Tablolar: dönem dökümü, kampanya performansı (kampanyasız dahil), en aktif 20 müşteri, personel (yalnızca yönetici). CSV indirme (Excel uyumlu: UTF-8 BOM, `;`, ondalık virgül); indirmeler denetim kaydına yazılır. "Ziyaret" = kazanım veya kullanım; "yeni müşteri" = şubedeki ilk ödülünü bu dönemde kazanan |

Şube paneli tamamlandı.

### 6.2. İşletme yöneticisi paneli (`/panel/isletme`) — yazıldı

| Ekran | Yol | Not |
|---|---|---|
| Genel bakış | `/panel/isletme` | Toplam (farklı) müşteri, son 30 gün işlem ve ödül, Pro şube sayısı, toplam bakiye; şube karşılaştırma tablosu (oran, bu ay müşteri/limit, müşteri, işlem, kazandırılan, bakiye, puan, durum, yönetici yoksa uyarı); son 6 ay ödül grafiği ve şubelere göre ödül çubukları. Şube yoksa başlangıç adımları |
| İşletme bilgileri | `/panel/isletme/bilgiler` | Ad, mutfak, açıklama, logo (600 px WebP). Adres (slug) değişmez. Ticari unvan/vergi bilgileri salt okunur |
| Şubeler | `/panel/isletme/subeler` (+ `yeni`, `{id}`) | Ekle: il → ilçe (JSON: `/panel/isletme/ilceler/{il}`), ad (ilçe adı önerilir), adres, telefon; adres `il-slug-ilçe-slug` (aynı ilçede `-2`, `-3`); oran %0, Ücretsiz paket; ekledikten sonra yönetici formuna gider. Ayrıntı: istatistik, paket, yönetici, oran/kampanya/saat/fotoğraf özeti. Aktif/pasif. Silme yalnızca hiç işlem görmemiş, süren paketi/bekleyen havalesi olmayan şubede (durum `deleted`, panel hesapları pasif) |
| Şube yöneticileri | `/panel/isletme/sube-yoneticileri` | Şube başına tek aktif yönetici (DB benzersiz indeksi ile de korunur). Ekle (şube, ad, e-posta, telefon, şifre ≥ 10), düzenle/şifre değiştir, pasifleştir/aktif et (başka aktif yönetici varsa aktif edilemez). Yöneticisiz şubeler uyarı olarak listelenir |
| Menü | `/panel/isletme/menu` (+ ürün formu) | Kategori ekle/düzenle/gizle/sil (ürünü olan silinmez); ürün ekle/düzenle (fiyat "1.234,50" biçimi, görsel 900 px WebP), satışa kapat/aç, sil (yumuşak), kategori ve ürünlerde ↑↓ sıralama. Değişiklik tüm şubelerin sayfasında hemen görünür |
| Raporlar | `/panel/isletme/raporlar` | Şube raporlarıyla aynı tarih aralığı seçimi; şube başına işlem, farklı müşteri, yeni müşteri, harcama, kazandırılan/kullanılan ödül, bakiye + toplam satırı (toplam müşteri tekil sayılır); iki çubuk grafik; CSV |
| Abonelikler | `/panel/isletme/abonelikler` | Salt okunur: şube paketleri ve bitişleri, bu ay müşteri/limit, Pro dönem geçmişi, havale bildirimleri |

Tüm web panelleri (platform, şube, işletme) tamamlandı.
