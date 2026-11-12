# Kumbare – Proje Rehberi

Restoranlar için dijital sadakat ("kumbara") platformu. Müşteri restoranda öder, harcamasının şubenin belirlediği oranı kadar ödül o şubenin kumbarasına eklenir; ödül yalnızca aynı şubede, müşterinin mobil uygulamadan onayıyla kullanılır.

Kullanıcıyla **Türkçe** konuşulur; kod yorumları, arayüz metinleri ve belgeler Türkçedir. Kullanıcı teknik olmayan dille, adım adım yönlendirme bekler.

## Kaynak belgeler (önce bunlara bak)

| Dosya | İçerik |
|---|---|
| `iş planı.txt` | Ürün gereksinimleri — **tüm kararlar işlenmiş güncel sürüm** (orijinali: `iş planı (orijinal).txt`) |
| `teknik-tasarim-db.md` | Veritabanı tasarımı |
| `teknik-tasarim-api.md` | Backend mimarisi, iş akışları, API uç noktaları, yetkiler |
| `teknik-tasarim-web.md` | Web sitesi ve paneller |
| `teknik-tasarim-mobile.md` | Mobil uygulama |
| `deploy/KURULUM-HOSTING.md` | cPanel kurulum ve güncelleme rehberi |

Plan ile tasarım çelişirse **iş planı + bu dosyadaki kararlar** geçerlidir.

## Temel iş kuralları (özet)

- **Her şube bağımsız bir restorandır**: ayrı kumbara, ayrı ödül oranı (%0–%100, yeni şubede %0), ayrı aylık müşteri sayacı, ayrı paket (10 şube = 10 ücretli paket). Menü ve fiyatlar markaya (işletmeye) aittir.
- Roller: platform yöneticisi, işletme (marka) yöneticisi (tek kişi), şube yöneticisi, çalışan (tek şubeye bağlı), müşteri.
- Müşteri numarası 1'den başlar, sıralı, boşluksuz, değiştirilemez, tekrar kullanılmaz.
- **Ödül kazanma** onaysızdır (ödeme sonrası, personel özet ekranından onaylar). **Ödül kullanma ve düzeltme** her zaman müşterinin mobil uygulamadan onayını gerektirir; onay süresizdir; müşteri reddederse kimse (platform dahil) düzeltme yapamaz.
- İptal/iade yok; hata, şube yöneticisinin müşteri onaylı **düzeltme kaydı** ile (ters hareket) giderilir. İşlem kayıtları silinmez.
- Ücretsiz paket: ayda N (varsayılan 100) farklı müşteri / şube; limit dolunca yalnızca o ay sayılmamış **yeni** müşteriye ödül kazandırılamaz; **ödül kullanımı limitten bağımsızdır**. Uyarılar limitin %80 / %90'ında. Profesyonel: sınırsız; aylık/yıllık; ödeme havale, platform yöneticisi elle aktif eder; süre dolunca otomatik Ücretsiz'e düşer.
- Kampanyalar şube bazında; ödül kazandırırken **personel uygun kampanyalardan birini seçer** (oranlar toplanmaz). Doğum günü kampanyası (29 Şubat → 28 Şubat), yeni müşteri, hafta içi. Ürün bazlı kampanya yok.
- Puan 1–10 **tam sayı**, şube bazında; yalnızca o şubede ödül kazanmış müşteri yorum yapabilir. Gösterim "8.7 / 10 — 245 değerlendirme", **yıldız yok**. "En yüksek puan" sıralamasında en az 10 değerlendirme şartı.
- Mobil: push bildirim **yok** (FCM kaldırıldı). Bildirimler (onay istekleri) ve Kampanyalar (kayıtlı ilçeye göre) yalnızca uygulama açılışında ve sayfadaki "Yenile" butonunda sunucudan çekilir.
- Kayıtta doğum tarihi alınır; müşteri değiştiremez; personel doğum tarihini görmez (yalnızca "Bugün doğum günü"). Personel müşteri numarasıyla yalnızca ad-soyad ve o şubedeki bakiyeyi görür.
- Arayüz yalnızca Türkçe, para birimi TL, kuruşlar korunur.

## Klasör yapısı

```
kumbare.com/
  backend/        Laravel 12 uygulaması (API + herkese açık web sitesi + paneller: platform yönetimi, şube)
  database/       kumbare-schema.sql (şemanın TEK kaynağı), kumbare-il-ilce.sql,
                  ornek-veri/ (generate.mjs → 01..08-ornek-veri.sql, 99-ornek-veriyi-sil.sql)
  deploy/         Yükleme paketleri (ZIP) ve KURULUM-HOSTING.md
  mobile/         (henüz yok) Expo uygulaması
```

### backend/ içinde önemli yerler
- `app/Domain/` — **tüm iş kuralları burada**: `Loyalty/` (EarnService, RedeemService, ApprovalService, CorrectionService, EligibleCampaignResolver, StaffGate), `Counter/MonthlyCounterService`, `Billing/PlanResolver`, `Platform/` (PaymentReviewService: havale onayı → abonelik/sponsorluk, BusinessReviewService: başvuru/askıya alma), `Customers/CustomerRegistrar`, `Reviews/`, `Catalog/BranchSearch`, `Support/Money` (kuruş tamsayı hesap).
- `app/Http/Controllers/Api/V1/` — mobil API (`/api/v1`), `Api/Panel/` — personel işlem API'si (`/api/panel/v1`; aynı `TransactionController` web işlem ekranında da oturumla kullanılır), `Web/` — herkese açık site, `Admin/` — platform yönetimi (`/yonetim`), `Panel/` — giriş + şube paneli (`/panel/...`).
- Panel erişimleri (bootstrap/app.php alias): `platform` (EnsurePlatformAdmin), `branch.manager` (EnsureBranchManager, şube kapalıysa `/panel`'e döner), `panel.staff` (EnsureBranchStaff: yönetici+çalışan, JSON isteklerde JSON hata), `business.owner` (EnsureBusinessOwner: onaylı işletmenin yöneticisi; işletme `$request->attributes->get('business')`). Şube nesnesi `$request->attributes->get('branch')`.
- `routes/api.php`, `routes/web.php` (WEB_DOMAIN mantığı; site + panel rotaları aynı alan adında), `routes/console.php` (zamanlama).
- Şablonlarda panel kullanıcısı `auth('web')->user()` ile okunur (`auth()->user()` testte Sanctum oturumunu döndürebilir).
- Görünümler: `resources/views/site/` (site), `layouts/admin.blade.php` + `admin/` (yönetim), `layouts/panel.blade.php` + `panel/` (şube), `layouts/auth.blade.php` (giriş). Ortak parçalar: `admin/partials/column-chart.blade.php` (SVG sütun grafiği), `admin/partials/bar-list.blade.php`, `admin/partials/reason-action.blade.php` (gerekçeli işlem düğmesi). Etiket/rozetler `App\Support\Labels`.
- CSS/JS: `public/css/site.css`, `public/js/site.js` (site); `public/css/panel.css`, `public/js/panel.js` (tüm paneller), `public/js/panel-islem.js` (kasa işlem ekranı). `public/fonts/` (self-host), `public/img/` (Unsplash, kaynaklar `public/img/KAYNAKLAR.txt`).
- Havale dekontları: `storage/app/private/dekontlar/{şube_id}/` (local disk, dışarıdan erişilemez; yalnızca denetleyici üzerinden açılır).
- Herkese açık yüklemeler (`public` disk): **`public/uploads/`** (storage:link/symlink yok). Şube fotoğrafları `uploads/subeler/{şube}/`, kampanya görselleri `uploads/kampanyalar/{şube}/`. `AppSupportImageStore` GD varsa en uzun kenarı 1600 px (kampanya 1200) olacak şekilde küçültüp WebP'ye çevirir, yoksa olduğu gibi kaydeder. URL = `APP_URL/uploads/...` (yerelde APP_URL portu farklıysa önizleme kırık görünür; canlıda sorun yok).
- `tests/` — Feature testleri `kumbare_test` veritabanında çalışır (DatabaseTransactions). Test yardımcısı `earnAs()` Sanctum oturumu açtığı için aynı testte web sayfası istenirken `actingAs($user, 'web')` yazılmalı.

## Teknik kurallar ve tuzaklar

- **Şema SQL dosyasıyla yönetilir; Laravel migration YOK. `php artisan migrate` ASLA çalıştırılmaz.** Şema değişikliği = `database/kumbare-schema.sql` güncelle + canlı için ayrı ALTER SQL'i hazırla.
- **PostgreSQL 10.23** (hem canlı hem yerel): PG11+ özellikleri kullanılmaz (ör. trigger'da `EXECUTE FUNCTION` değil `EXECUTE PROCEDURE`).
- Canlıda tabloların sahibi cPanel ana kullanıcısı; uygulama kullanıcısına GRANT + DEFAULT PRIVILEGES verildi (yeni tablo eklenirse yetki kontrol edilmeli).
- Para hesapları `App\Domain\Support\Money` ile kuruş tamsayısı olarak; float kullanılmaz.
- Tarih/saat: `Europe/Istanbul`, DB bağlantısı da bu saat diliminde (`config/database.php` → `timezone`).
- Hosting **LiteSpeed**: `.htaccess`'e riskli direktif eklenmez; güvenlik başlıkları `SecurityHeaders` middleware'inde.
- Blade/PHP açıklama bloklarına `*/` içeren metin (ör. cron ifadesi `*/5`) yazma — dosyayı bozar.
- Blade yönergesi (`@if`, `@endif` …) bir harfe bitişik yazılırsa (`kullanım@if`, `düzeltme@endif`) yönerge sayılmaz → sayfa ParseError verir. Yönergeden önce boşluk bırak.
- İşlem defteri (`wallet_transactions`) UPDATE/DELETE'e kapalıdır (trigger); testte eski tarihli işlem gerekiyorsa `$this->travel(-N)->days()` ile oluştur.
- Doğrulama mesajları Türkçe ve ilk harf büyük (`TurkishText::ucfirst`, i→İ) — hem API hem web formları için `bootstrap/app.php`'deki ValidationException işleyicisinde yapılır.
- Türkçe sayı ekleri ("3'ü", "5'i") sayıya göre değişir; arayüz metinlerinde sayıdan sonra ek gerektirmeyen cümle kurulur.
- Panel tasarımı: sade Blade + `panel.css` (Livewire/Filament/Tailwind yok). Grafik renkleri marka ailesinden doğrulanmış tonlar: yeşil `#00897b`, koyu sarı `#b98600` (marka renkleri grafikte kontrast/renk körlüğü kontrolünü geçmiyor). `[hidden]` CSS'te `display:none !important` (aksi halde `display:flex` alanlar gizlenmez).
- Panelde aynı sayfada tekrar tekrar gönderilen formlara `data-no-guard` eklenir (panel.js çift gönderim engeli formu kilitler).
- Web sitesi tasarımı: "adisyon + kumbara" konsepti; renkler mürekkep `#0e3b3a`, kumbara sarısı `#f2b705`, kâğıt `#f6f7f4`; yazı tipleri Bricolage Grotesque + Source Sans 3; restoran fotoğrafı yerine mutfağa göre renkli "tabela"; mutfak fotoğrafları "temsilî" olarak etiketlenir. Google Görseller kullanılmaz (lisans).

## Git / GitHub

- Depo: **https://github.com/shortubeco/kumbare** (özel/private), dal `main`. Proje kökü (`C:\claude-lessons\kumbare.com`) depodur; ilk yükleme 12 Kasım 2026.
- Depoya girmeyenler (`.gitignore`): `deploy/*.zip` (kumbare-backend.zip içinde `.env` var!), `backend/public/uploads/`, `backend/.env`, `backend/vendor`, `storage` içerikleri. Göndermeden önce `git diff --cached` ile şifre/anahtar taraması yapılır.
- Kullanıcı istediğinde commit + push yapılır (kendiliğinden değil). Commit mesajları Türkçe.

## Yerel geliştirme ortamı (Windows)

- PHP 8.2.35 + Composer: `C:\php82` (kullanıcı PATH'inde). XAMPP'teki PHP 8.0'a dokunulmaz.
- PostgreSQL 10.23: `C:\pgsql10` — **Windows hizmeti değil**; kullanıcı `C:\pgsql10\baslat.bat` ile başlatır (bilgisayar yeniden başlayınca gerekir). Şifre: `C:\pgsql10\yerel-sifre.txt`. Veritabanları: `kumbare` (örnek veri yüklü), `kumbare_test`.
- Yerel platform yöneticisi: `admin@local.test` (ayrıca tarayıcı kontrolleri için `panel-qa@local.test`, yalnızca yerel).
- Testler: `backend` klasöründe `php artisan test` (son durum: **116 test geçiyor**). Testler toplu düşerse önce PostgreSQL'in açık olduğunu kontrol et.
- Yerel sunucu: `php artisan serve --host=127.0.0.1 --port=8010`.
- Python yok; betikler için Node.js (v22) kullanılır.

## Canlı ortam (cPanel, SSH yok)

| | Adres |
|---|---|
| API | https://kumbareapi.trthabercanli.com (`/api/v1`, `/api/panel/v1`) |
| Web sitesi | https://kumbare.trthabercanli.com |

- cPanel kullanıcı adı **`sozcpdjd`** (ev klasörü `/home/sozcpdjd`). Kullanıcıya verilen cron komutlarında yer tutucu (`KULLANICI`) değil bu ad yazılır.
- Uygulama: `/home/sozcpdjd/kumbare-backend`; her iki alan adının kökü `kumbare-backend/public`. `.env`: `APP_URL=https://kumbare.trthabercanli.com`, `WEB_DOMAIN=kumbare.trthabercanli.com`.
- Veritabanı: `sozcpdjd_kumbareDB`, kullanıcı `sozcpdjd_kumbareuser`.
- Artisan komutları **tek seferlik cPanel cron görevi** ile çalıştırılır (çıktı bir .txt dosyasına yazdırılır, sonra görev silinir). PHP yolu: **`/opt/alt/php82/usr/bin/php`** (düz `php` cgi-fcgi'dir, kullanılmaz).
- Kalıcı cron: her 5 dakikada `schedule:run` (cPanel en sık 5 dk'ya izin veriyor → zamanlanmış görev saatleri 5'in katı dakikalar olmalı).
- SMTP: `noreply@trthabercanli.com`; `MAIL_HOST` = sunucunun kendi adı (`serverXXX.web-hosting.com`; sertifika `*.web-hosting.com`), port 465 smtps. AutoSSL kapalı.
- Platform yöneticisi: hakanegne@gmail.com (oluşturuldu).
- **Güncelleme yöntemi:** yalnızca değişen dosyalar `kumbare-backend/...` klasör yapısıyla scratchpad'de toplanıp ZIP'lenir (`tar -a -c -f deploy/guncelleme-N-ad.zip kumbare-backend` — PowerShell Compress-Archive kullanılmaz, ters bölü sorunu), kullanıcı File Manager'da ev klasörüne Extract eder. **Rota eklendi/değiştiyse** cron ile `route:cache` (+ `view:cache`) şart; yalnızca denetleyici/görünüm/CSS/JS değiştiyse cron gerekmez (görünümler dosya tarihine göre kendiliğinden yenilenir). `.env` değişirse `config:cache` şart. Son paket: `guncelleme-20-menu-kategori-silme.zip` (yüklenmeyi bekliyor; yalnızca denetleyici, cron gerekmez). 19 ve öncesi canlıda.
- Aynı tarayıcıda tek panel oturumu olur: şube yöneticisiyle girilmişken `/yonetim` açılırsa 403 sayfası (errors/403.blade.php) kimle giriş yapıldığını gösterir ve "Çıkış yap ve başka hesapla gir" sunar. Kullanıcı hesap değiştirmeyi unutabilir — erişim sorununda önce hangi hesapla giriş yapıldığına bak.
- Tarayıcıyla canlı test: kullanıcı Chrome'da ilgili hesapla (yönetici veya örnek şube yöneticisi) giriş yapar, Claude yeni sekmede test eder. Claude canlı sitede **hesap açamaz / şifre giremez** (ör. işletme başvurusu formu) — bu adımları kullanıcı yapar.
- Canlıda **örnek veri yüklü** (81 il × 100 şube, 2000 müşteri; tüm örnek hesap şifreleri `Kumbare2026!`, e-postalar `@example.com`; şube yöneticileri `sube{şube_id}@example.com`, işletme yöneticileri `isletme{işletme_id}@example.com`). Test için kullanılan şubeler: `sube301` (Közbaşı Kebap – Tutak, testlerde Pro'ya geçirildi), `sube3400` (Salih Usta Çorba Evi – Şişli, en hareketli). Gerçek kullanıma geçmeden `database/ornek-veri/99-ornek-veriyi-sil.sql` çalıştırılmalı.
- Canlı testlerde örnek veride bırakılan izler: "Deneme Mutfağı (pasif)" kategorisi, iptal edilmiş abonelik #120 ve sponsorluk #1, kaldırılmış kampanya #1999, havale bildirimi #1 (onaylı, Tutak Pro) ve #2 (reddedildi), müşteri 1699'a Tutak'ta +10 TL ödül, müşteri 987 (Şişli) işlem #8431 için geri çekilmiş +5 TL düzeltme isteği; Şişli'de iki oran değişikliği (10→12,5→10), sona erdirilmiş "Canlı test %15 kampanya", yorum #1599'a test cevabı ve açık bir test şikâyeti (yönetimden kapatılabilir); Közbaşı Kebap'ta gizli "Canlı test kategorisi" (güncelleme-20 sonrası menüden silinebilir) ve silinmiş (status deleted) "Canlı Test Şubesi" (#8101, ankara-cankaya). Ayarlardaki banka bilgileri deneme değerleri ("Test Bankası", IBAN TR000…) — gerçek bilgilerle değiştirilmeli.
- Canlı QA: `node deploy/qa/canli-qa.mjs` (yönetim sayfaları için isteğe bağlı `KUMBARE_QA_ADMIN_EMAIL` / `KUMBARE_QA_ADMIN_PASSWORD` ortam değişkenleri) — son çalıştırmada **168/168 kontrol** geçti (API + web sitesi + panel girişi/yetki). Örnek verinin tutarlılık kontrolü: `database/ornek-veri/tutarlilik-kontrol.sql` (psql ile). Yazma testleri yalnızca örnek hesaplarla yapılır; yeni müşteri kaydı oluşturulmaz (numara tüketir).

## Durum (12 Kasım 2026)

**Tamamlandı ve canlıda**
- İş planı netleştirildi, teknik tasarım (4 belge), PostgreSQL şeması + il/ilçe verisi.
- Backend: modeller, iş kuralı servisleri, mobil API, personel işlem API'si, zamanlanmış komutlar, `kumbare:create-admin`.
- Canlı QA ve 6 düzeltme (güncelleme-1). Herkese açık web sitesi (güncelleme-2, -3, -4).
- **Ortak panel girişi** `/panel/giris` (+ şifremi unuttum, çıkış). Platform yöneticisi → `/yonetim`; işletme yöneticisi (onaylı işletme) → `/panel/isletme`; şube yöneticisi ve çalışan → `/panel/islem`; bekleyen/reddedilen/askıdaki işletme veya kapalı şube kullanıcıları → bilgi sayfası (`panel/coming-soon`).
- **Platform yönetimi `/yonetim`** (güncelleme-5…-10): genel bakış (rakam kutuları + müşteri/işlem ve işletme grafikleri), başvurular, işletmeler/şubeler (askıya alma, kapatma, şube pasif), müşteriler (arama — tam numara önce, pasifleştirme, doğum tarihi düzeltme), havale bildirimleri (onay → abonelik/sponsorluk), abonelikler, sponsorluklar, kampanya moderasyonu, yorum şikâyetleri, kategoriler, paketler ve ayarlar, denetim kayıtları + numara sorgu raporu. Canlıda okuma ve yazma işlemleri Chrome ile test edildi.
- **Şube paneli** (`layouts/panel.blade.php`):
  - Paket ve ödeme `/panel/paket`, Sponsorluk `/panel/sponsorluk`, Havale bildirimi `/panel/havale-bildirimi` (dekont yükleme) — yalnızca şube yöneticisi (güncelleme-11). Havale akışı canlıda uçtan uca test edildi.
  - Kasa **İşlem ekranı** `/panel/islem` — yönetici + yetkili çalışan; müşteri bul → ödül kazandır (kampanya seçimi, canlı özet, idempotency) / ödül kullandır (istek → müşteri onayı beklenir, otomatik sorgu, geri çekme) (güncelleme-13). Canlıda test edildi; müşteri onayı adımı mobil uygulama gelince uçtan uca denenecek (yerelde onay simülasyonuyla çalışıyor).
  - Şube **Genel bakış** `/panel/genel-bakis` — yalnızca yönetici; aylık müşteri/limit, paket, işlem sayıları, ödül toplamları, 2 grafik, en aktif müşteriler (güncelleme-14). Canlıda kontrol edildi.
- Hata mesajları web formlarında da büyük harfle (güncelleme-12); başvuru formu bot tuzağı alanı tarayıcı otomatik doldurmasına karşı yeniden adlandırıldı ve engellenen gönderimler denetime yazılıyor (güncelleme-7/8).

**Sıradaki adım (web paneli devamı, kullanıcının seçtiği sıra)**
1. ~~Şube Müşteriler ve Düzeltmeler~~ yazıldı: `/panel/musteriler` (+ `/{no}` ayrıntı; yönetici + `view_customers` yetkili çalışan; telefon/e-posta/doğum tarihi gösterilmez), `/panel/duzeltmeler` (+ `/yeni?islem=ID`, geri çekme; yalnızca yönetici; `CorrectionService` kuralları). Canlıda (güncelleme-15), Şişli şubesiyle (sube3400) canlı test edildi: arama, sıralama, ayrıntı, gizlilik, başka şube 404, sınır aşımı engeli, istek gönderme, tekrar engeli, geri çekme. Müşteri onayı adımı mobil uygulamayla denenecek.
2. ~~Şube ayarları~~ yazıldı (yalnızca yönetici, menüde "Ayarlar"): Ödül oranı `/panel/odul-orani` (geçmiş tablosu), Kampanyalar `/panel/kampanyalar` (oran/duyuru, gün, yeni müşteri, doğum günü, görsel, taslak/yayın, sona erdirme), Çalışanlar `/panel/calisanlar` (yönetici şifre belirler, yetki işaretleme, pasifleştirme), Şube bilgileri `/panel/sube-bilgileri` (adres, iletişim, konum + "konumumu kullan", çalışma saatleri, en fazla 10 fotoğraf, kapak seçimi), Değerlendirmeler `/panel/degerlendirmeler` (dağılım, filtre, cevap yaz/düzenle, şikâyet et), Bildirimler `/panel/bildirimler` (yönetici + çalışan; menüde okunmamış rozeti). Sitede şube sayfası kendi fotoğrafını kapak + galeri olarak gösterir. Canlıda (güncelleme-16) ve Şişli şubesiyle canlı test edildi: 6 ekran açılıyor; fotoğraf yükleme canlı sunucuda WebP'ye çevirdi (GD var), site kapağı ve mobil API fotoğrafı gösterdi, silinince dosya da silindi; oran %150 reddedildi, %12,5↔%10 geçmişe yazıldı; oran kampanyası yayımlandı → işlem ekranında seçildi → sona erdirildi; yorum cevabı yazıldı/düzenlendi, sitede göründü; şikâyet gönderildi. Çalışan ekleme canlıda denenmedi (hesap açma kullanıcıda).
3. ~~Şube Raporları~~ yazıldı: `/panel/raporlar` (yönetici + `view_reports` yetkili çalışan; hazır aralıklar 7g/30g/bu ay/geçen ay/90g/bu yıl + özel ≤366 gün; döküm ≤45 gün günlük, ≤200 gün haftalık, üstü aylık; özet kutuları, müşteri (yeni/tekrar) ve ödül grafikleri, dönem tablosu, kampanya performansı, en aktif müşteriler, yöneticiye personel tablosu; CSV: UTF-8 BOM, `;`, ondalık virgül). İş kuralı `Domain/Reports/BranchReport`. Canlıda (güncelleme-18) ve Şişli ile canlı test edildi (6 hazır aralık, hatalı aralık uyarısı, 3 CSV: BOM, `;`, ondalık virgül). **Şube paneli tamamlandı.**
4. ~~İşletme yöneticisi paneli~~ yazıldı (`/panel/isletme`, denetleyiciler `Panel/Business/`): Genel bakış (toplamlar + şube karşılaştırma tablosu + 2 grafik), Şubeler (ekle: il/ilçe, adres `il-ilçe[-n]`, oran %0; ayrıntı; aktif/pasif; yalnızca işlemsiz şube silinir → status deleted + panel hesapları pasif), Şube yöneticileri (şube başına tek aktif; ekle/düzenle/şifre/pasifleştir), Menü (kategori ekle/düzenle/gizle/sil, ürün ekle/düzenle/görsel/satışa kapat/yumuşak sil, ↑↓ sıralama), Raporlar (tarih aralığı, şube karşılaştırması + toplam, CSV), Abonelikler (salt okunur), İşletme bilgileri (ad, mutfak, açıklama, logo; slug değişmez). Tarih aralığı kodu ortak: `Panel/Concerns/ReportRange`. Canlıda (güncelleme-19) ve `isletme1` (Közbaşı Kebap, 14 şube) ile canlı test edildi: 7 ekran, menü kategori/ürün ekleme → sitede görünme → "Şu an yok" → silme, şube ekleme (`ankara-cankaya`, %0, yönetici formuna yönlendirme) → pasif (site 404) → silme, işlem görmüş şube (Tutak) silme engeli, rapor + CSV toplamı. Bulunan eksik: ürünü silinmiş kategori silinemiyor, yalnızca gizleniyordu → düzeltildi (yumuşak silinmiş ürünler kalıcı silinir; menu_items'a bağlı tablo yok), paket guncelleme-20. **Tüm web panelleri tamamlandı.**
5. Sonra **mobil uygulama** (Expo) — bkz. `teknik-tasarim-mobile.md`.

**Bekleyen test**
- Başvuru onay/ret canlı testi: kullanıcının `/isletmeler-icin`'den iki deneme başvurusu göndermesi bekleniyor (önerilen değerler: Deneme Onay / Deneme Ret Restoran, `deneme-onay@example.com`, `deneme-ret@example.com`).

**Açık notlar**
- Mobil (telefon) görünüm ekran görüntüsüyle doğrulanamadı (tarayıcı penceresi küçültülemiyor); site ve paneller için kullanıcının telefondan bakması istendi.
- KVKK metni genel şablon; hukuki inceleme gerekiyor.
- Uygulama mağaza butonları "Yakında".
- Google Search Console kaydı gerçek verilere geçince yapılmalı.
