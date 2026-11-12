# Kumbare – Teknik Tasarım: Backend ve API

> Kaynak: `iş planı.txt`. Diğer parçalar: `teknik-tasarim-db.md` (veritabanı), `teknik-tasarim-web.md` (web sitesi ve paneller), `teknik-tasarim-mobile.md` (mobil uygulama).
> Bu belge kod içermez.

---

## 1. Genel Mimari

```
┌──────────────────────┐        ┌──────────────────────────────────────────────┐
│  Mobil Uygulama      │  HTTPS │  Laravel Uygulaması (tek kod tabanı)         │
│  React Native / Expo │───────►│                                              │
│  (iOS + Android)     │  JSON  │  ├─ /api/v1/*      → Mobil API (Sanctum)     │
└──────────────────────┘        │  ├─ /              → Herkese açık web (Blade)│
                                │  ├─ /panel/*       → İşletme/Şube/Çalışan    │
                                │  └─ /yonetim/*     → Platform yöneticisi     │
                                │                                              │
                                │  Zamanlanmış görevler: cron → schedule:run   │
                                │  Kuyruk: database driver                     │
                                └───────────────────┬──────────────────────────┘
                                                    │
                                                    ▼
                                        ┌──────────────────────┐
                                        │  PostgreSQL          │
                                        └──────────────────────┘
```

Push bildirim servisi (FCM vb.) **kullanılmaz.** Sunucu mobil uygulamaya hiçbir zaman kendiliğinden veri göndermez; bütün veriyi uygulama ister (uygulama açılışı ve Yenile butonları). Periyodik arka plan isteği de yoktur.

| Konu | Karar |
|---|---|
| Backend | Tek Laravel uygulaması (monolit): web sitesi, paneller ve mobil API aynı kod tabanında |
| Mobil API kimlik doğrulama | Laravel Sanctum (kişisel erişim token'ı) |
| Panel kimlik doğrulama | Oturum tabanlı web guard'ları (`panel`, `admin`) |
| Kuyruk | `database` driver |
| Dil / para birimi | Türkçe / TL, sabit |

### 1.1. Shared Hosting Kısıtları ve Çözümleri

| Kısıt | Çözüm |
|---|---|
| Sürekli çalışan süreç (queue worker, websocket) yok | Cron her dakika `schedule:run` çalıştırır; kuyruk işleri scheduler içinden `queue:work --stop-when-empty` ile tüketilir |
| Websocket yok | Personel paneli onay durumunu birkaç saniyede bir sorgular; mobil Bildirimler ekranı açılınca sorgular |
| Composer / Node build sunucuda olmayabilir | `vendor/` ve derlenmiş asset'ler deploy paketine dahil edilir |
| PostGIS olmayabilir | "Yakındaki restoranlar" için Haversine hesabı SQL'de yapılır, bounding-box ön filtresiyle |
| Dosya depolama | `storage/app/public` + `public/storage` bağlantısı; yükleme sırasında yeniden boyutlandırma |

Gereken minimum ortam: PHP 8.2+, PostgreSQL 10+, cron erişimi, `pdo_pgsql`, `gd` veya `imagick`, `openssl`, `curl`.

---

## 2. Proje Yapısı

```
app/
  Domain/
    Loyalty/        EarnService, RedeemService, CorrectionService,
                    EligibleCampaignResolver, ApprovalService
    Counter/        MonthlyCounterService (limit kontrolü, eşik bildirimleri)
    Billing/        PlanResolver, SubscriptionService, PaymentNotificationService,
                    SponsorshipService
    Catalog/        Business, Branch, Menu, Campaign servisleri
    Reviews/        ReviewService, RatingAggregator
    Customers/      CustomerNumberGenerator, CustomerAccountService
    Notifications/  PanelNotifier (yalnızca panel içi bildirimler)
  Models/
  Http/
    Controllers/Api/V1/     mobil API
    Controllers/Web/        herkese açık site
    Controllers/Panel/      işletme/şube/çalışan paneli
    Livewire/Panel/         işlem ekranı, menü düzenleyici vb.
    Resources/              API JSON dönüşümleri
    Requests/               doğrulama kuralları
  Filament/                 platform yönetim paneli
  Policies/
  Jobs/                     NotifyCounterThreshold, ...
  Console/Commands/         zamanlanmış komutlar
database/  seeders/   (şema SQL ile yönetilir; Laravel migration kullanılmaz)
routes/    web.php  panel.php  admin.php  api.php
```

Depo düzeni:
```
kumbare.com/
  backend/    Laravel 12 uygulaması (yukarıdaki yapı)
  mobile/     Expo projesi
  database/   kumbare-schema.sql, kumbare-il-ilce.sql (şemanın tek kaynağı)
  iş planı.txt, teknik-tasarim-*.md
```

İş kuralları (earn, redeem, düzeltme, sayaç, kampanya uygunluğu) yalnızca `Domain` servislerinde bulunur. Panel ve API aynı servisleri çağırır.

---

## 3. Kritik İş Akışları

### 3.1. Müşteri Kaydı

1. Doğrulama: ad, soyad, telefon (benzersiz), il, ilçe (ile ait olmalı), doğum tarihi (geçmiş bir tarih), e-posta (benzersiz), şifre ve şifre tekrarı (eşleşmeli), KVKK onayı.
2. Tek transaction'da müşteri numarası üretilir (bkz. `teknik-tasarim-db.md` 4.1) ve müşteri oluşturulur.
3. Sanctum token'ı ile müşteri numarası döner. E-posta doğrulaması yapılmaz.

### 3.2. Müşteri Numarası Sorgulama (panel)

- Girdi: müşteri numarası. Çıktı: müşterinin **adı-soyadı**, bu şubedeki bakiyesi, bu şubeye ait toplamlar ve bugün doğum günü olup olmadığı (`is_birthday_today`). Doğum tarihinin kendisi personele gösterilmez.
- Başka şubelerin kumbaraları hiçbir zaman döndürülmez.
- Her sorgu `audit_logs` tablosuna yazılır. Personel başına hız sınırı uygulanır (ör. dakikada 20).

### 3.3. Ödül Kazandırma (Earn)

Ön koşullar: personelin `earn` yetkisi olması, şubenin aktif olması, uygulanacak oranın %0'dan büyük olması.

1. Personel müşteri numarasını girer, müşterinin adı-soyadı ve bakiyesi gösterilir.
2. Personel ödenen harcama tutarını girer.
3. Sunucu bu müşteri için **uygun kampanyaları** listeler (bkz. 3.4). Personel listeden birini seçer ya da "Kampanya yok" seçeneğini bırakır.
4. Sunucu **önizleme** hesaplar: uygulanacak oran, seçilen kampanya, kazanılacak ödül ve limit durumu. Personele özet ekranı gösterilir.
5. Personel onaylar. Ardından tek bir DB transaction'ı çalışır:
   1. `monthly_branch_counters` (şube, bu ay) satırı yoksa oluşturulur ve `FOR UPDATE` ile kilitlenir.
   2. Müşterinin bu ay zaten sayılıp sayılmadığı `monthly_branch_customers` tablosundan kontrol edilir.
   3. Müşteri sayılmamışsa, şube Free paketteyse ve `unique_customers >= limit` ise **işlem reddedilir** ("Aylık müşteri limiti doldu").
   4. Müşteri sayılmamışsa `monthly_branch_customers` tablosuna eklenir ve `unique_customers` 1 artar.
   5. Seçilen kampanya varsa uygunluğu yeniden doğrulanır. Uygun değilse işlem hata verir ve personel yeniden seçim yapar. Uygulanacak oran kampanyanın oranı ya da şubenin `reward_rate` değeridir.
   6. Ödül hesaplanır: `round(spend_amount × rate / 100, 2)`.
   7. Wallet yoksa oluşturulur ve `FOR UPDATE` ile kilitlenir. Bakiye ve `total_earned` güncellenir.
   8. `wallet_transactions` tablosuna `earn` kaydı eklenir: harcama, oran, `campaign_id`, `balance_after`, `idempotency_key`.
6. Commit sonrasında kuyrukta eşik kontrolü yapılır. `unique_customers` limitin %80'ine, %90'ına veya %100'üne ilk kez ulaştıysa ilgili alan işaretlenir ve şube yöneticisine panel bildirimi oluşturulur.

Personelin seçtiği kampanya ve uygulanan oran işlem kaydına yazılır. Daha sonraki oran veya kampanya değişiklikleri geçmiş işlemleri etkilemez.

### 3.4. Kampanya Uygunluğu

Personelin seçim listesinde yalnızca şu koşulları sağlayan kampanyalar görünür:
- Şubeye ait, `type = 'rate_override'` ve `status = 'active'` olmalı,
- `now()` başlangıç ve bitiş tarihleri arasında olmalı,
- `weekdays` doluysa bugünün haftanın günü listede olmalı,
- `new_customers_only` ise müşterinin bu şubede daha önce earn işlemi olmamalı,
- `birthday_only` ise bugün (Europe/Istanbul) müşterinin doğum günü olmalı: gün ve ay `birth_date` ile eşleşmeli. 29 Şubat doğumlular artık olmayan yıllarda 28 Şubat'ta doğum günü sayılır.

Doğum günü kampanyalarını şube yöneticisi oluşturur. Bu kampanyalar müşterinin doğum günü değilse listede hiç görünmez.

Kurallar:
- Yalnızca bir kampanya seçilebilir. Oranlar toplanmaz.
- Varsayılan seçim "Kampanya yok"tur. Bu durumda şubenin `reward_rate` değeri uygulanır.
- Şube oranı %0 olsa bile uygun bir kampanya seçilirse kampanya oranı üzerinden ödül verilebilir.
- `announcement` tipi kampanyalar bu listede yer almaz; yalnızca duyuru amaçlıdır.

### 3.5. Ödül Kullandırma (Redeem)

1. Personel müşteri numarasını girer, müşterinin adı-soyadı ve bakiyesi gösterilir.
2. Personel kullanılacak tutarı girer. Sunucu bakiyeyi kontrol eder ve özet ekranı gösterilir.
3. Personel isteği gönderir ve `approval_requests` kaydı (type=`redeem`, status=`pending`) oluşturulur. **Bakiye henüz değişmez.**
4. Personel ekranı isteğin durumunu birkaç saniyede bir sorgular.
5. Müşteri mobil uygulamada Bildirimler ekranını açar ve isteği görür.
6. Müşteri **onaylarsa** tek transaction çalışır:
   1. İstek `FOR UPDATE` ile kilitlenir ve hâlâ `pending` olduğu kontrol edilir.
   2. Wallet kilitlenir ve bakiye yeniden kontrol edilir. Bakiye yetersizse istek `failure_reason` ile birlikte `rejected` yapılır.
   3. Bakiye düşülür, `total_redeemed` artar ve `wallet_transactions` tablosuna `redeem` kaydı eklenir.
   4. İstek `approved` yapılır.
7. Personel ekranında "Onaylandı" görünür. Personel indirimi uygular ve kalan tutarı tahsil eder.

Kurallar:
- Aylık limit bu akışı kontrol etmez ve sayacı etkilemez.
- Oranı %0 olan şubede de birikmiş bakiye kullanılabilir.
- Aynı kumbara için birden fazla `pending` istek olabilir; her biri onay anındaki bakiyeye göre değerlendirilir.
- Personel onaylanmamış bir isteği `withdrawn` durumuna çekebilir. İsteklerin süre sınırı yoktur.
- Ödül kullanımından sonra yapılan ödül kazanımı ayrı bir earn işlemidir ve yalnızca müşterinin ödediği tutar üzerinden hesaplanır.

### 3.6. Düzeltme (Correction)

1. Şube yöneticisi bir işlemi seçer, işaretli düzeltme tutarını ve açıklamayı girer. Özet ekranı gösterilir.
2. `approval_requests` kaydı oluşturulur (type=`correction`, `related_transaction_id`, `reason`).
3. Müşteri Bildirimler ekranında orijinal işlemi, düzeltme tutarını ve açıklamayı görür.
4. Müşteri **onaylarsa** wallet kilitlenir, sonuç bakiyenin 0 veya üzerinde olduğu kontrol edilir ve `correction` kaydı eklenir. `total_earned` ya da `total_redeemed`, düzeltilen işlemin türüne göre güncellenir.
5. Müşteri **reddederse** bakiye değişmez. Platform yöneticisi dahil hiçbir rol onaysız düzeltme yapamaz ve itiraz akışı yoktur.

Düzeltmeler aylık sayacı etkilemez.

### 3.7. Paket ve Limit

- Geçerli paketi `PlanResolver` belirler. Aktif ve süresi içinde bir Pro aboneliği varsa şube Pro'dadır, yoksa Free'dedir.
- Limit `settings.free_monthly_customer_limit` değerinden okunur ve tüm şubeler için aynıdır.
- Uyarı eşikleri `ceil(limit × warning_threshold_1 / 100)` ve `ceil(limit × warning_threshold_2 / 100)` ile hesaplanır.
- Limit ay ortasında değişirse yeni değer hemen geçerli olur. O ana kadar sayılmış müşteriler etkilenmez.
- Pro'dan Free'ye geçişte içinde bulunulan ayın sayacı korunur. Sayaç limitin üzerindeyse o ay yalnızca zaten sayılmış müşterilere earn yapılabilir.
- Havale akışı: Şube yöneticisi `payment_notifications` kaydı oluşturur. Platform yöneticisi onaylayınca `subscriptions` (veya `sponsorships`) kaydı oluşturulur ve şube yöneticisine bildirim gider.

### 3.8. Kampanya Listesi (mobil)

- `GET /campaigns`, müşterinin kayıtlı ilçesindeki şubelerin `status = 'active'` ve tarih aralığı içindeki kampanyalarını döner.
- Mobil uygulama bu isteği yalnızca açılışta ve Kampanyalar sayfasındaki Yenile butonunda gönderir. Push bildirim yoktur.
- Doğum günü ve yeni müşteri kampanyaları da listede görünür (koşul açıklamasıyla); bu koşulların uygunluğu yalnızca işlem anında, panelde değerlendirilir.

### 3.9. Puanlama

- Yorum veya puan yazabilmek için müşterinin bu şubede en az bir `earn` işlemi olmalıdır.
- Puan 1–10 arası tam sayıdır. İkinci gönderim mevcut değerlendirmeyi günceller.
- Her ekleme, güncelleme veya kaldırmada `branches.rating_avg` (bir ondalık) ve `rating_count` yeniden hesaplanır.
- Sıralamalar:
  - **En yüksek puan:** `WHERE rating_count >= 10 ORDER BY rating_avg DESC, rating_count DESC`
  - **En çok puanlanan:** `ORDER BY rating_count DESC`
- Puansız şubeler puana göre sıralamalarda sona alınır.
- Görünen ad varsayılan olarak "Ad S." biçimindedir. Müşteri isterse tam adını gösterebilir.

### 3.10. Popülerlik

`popularity_score` günlük olarak **son 30 gündeki işlem sayısı** şeklinde hesaplanır. "En popüler" sıralaması bu alana göre yapılır.

---

## 4. Zamanlanmış Görevler

| Görev | Sıklık | İş |
|---|---|---|
| Kuyruk tüketimi | Her dakika | `queue:work --stop-when-empty` |
| Kampanya durumları | Her 15 dk | Bitişi geçen kampanyaları `ended` yapar |
| Abonelik bitişi | Günlük 00:05 | Süresi geçenleri `expired` yapar ve bildirim üretir; bitişine 7 gün kalanlara uyarı verir |
| Sponsorluk dönemi | Her ayın 1'i 00:01 | Yeni ayın onaylı sponsorluklarını `active`, bitenleri `ended` yapar |
| Popülerlik puanı | Günlük 03:00 | `popularity_score` yeniden hesaplanır |
| Sitemap | Günlük | `sitemap.xml` yeniden üretilir |

Aylık sayaç için zamanlanmış görev gerekmez.

---

## 5. Yetkilendirme

| Kaynak / İşlem | Platform yön. | İşletme yön. | Şube yön. | Çalışan | Müşteri |
|---|---|---|---|---|---|
| İşletme bilgileri | ✔ (tümü) | ✔ (kendi) | — | — | — |
| Şube ekle/sil, şube yöneticisi oluştur | ✔ | ✔ (kendi şubeleri) | — | — | — |
| Şube bilgileri, saatler, görseller | ✔ | ✔ | ✔ (kendi) | — | — |
| Menü | ✔ | ✔ | görüntüleme | — | — |
| Ödül oranı | — | görüntüleme | ✔ | — | — |
| Kampanya oluşturma | moderasyon | görüntüleme | ✔ | — | — |
| İşlemde kampanya seçme | — | — | ✔ | `earn` | — |
| Çalışan ekle / yetki ver | — | görüntüleme | ✔ | — | — |
| Müşteri numarası sorgula | — | — | ✔ | `lookup_customer` | — |
| Earn | — | — | ✔ | `earn` | — |
| Redeem isteği | — | — | ✔ | `redeem` | onay/ret |
| Düzeltme isteği | — | — | ✔ | — | onay/ret |
| Müşteri listesi / geçmiş | ✔ (tümü) | ✔ (şube bazında) | ✔ (kendi şubesi) | `view_customers` | kendi kumbaraları |
| Raporlar | ✔ (tümü) | ✔ (şube + toplam) | ✔ (kendi şubesi) | `view_reports` | — |
| Havale bildirimi gönder | — | — | ✔ | — | — |
| Paket / sponsorluk aktif et | ✔ | — | — | — | — |
| Yorum yaz | — | — | — | — | ✔ (earn şartıyla) |
| Yoruma cevap | — | — | ✔ | — | — |
| Yorum kaldır | ✔ | — | — | — | — |

Uygulama: Laravel Policy ve Gate. Her panel sorgusu kullanıcının `business_id` / `branch_id` değeriyle **kapsam filtresinden** geçer; böylece başka bir şubenin verisine URL değiştirilerek erişilemez.

---

## 6. Güvenlik

- **Müşteri numarası taraması:** Numaralar sıralı olduğu için ad-soyad listesi çıkarılabilir. Bunu önlemek için personel başına hız sınırı uygulanır, tüm sorgular `audit_logs`'a yazılır ve platform panelinde olağandışı sorgu raporu bulunur.
- **Bakiye:** Bakiyeyi azaltan her işlem (redeem, eksi yönlü düzeltme) müşteri onayı gerektirir. Bakiyeyi artıran tek onaysız işlem earn'dür.
- **Bütünlük:** CHECK kısıtları, değiştirilemez işlem defteri, satır kilitleri ve idempotency anahtarı (bkz. `teknik-tasarim-db.md` bölüm 4).
- **Kimlik doğrulama:** Şifreler bcrypt/argon2 ile hash'lenir ve girişe hız sınırı uygulanır. Şifre sıfırlama, e-postaya gönderilen tek kullanımlık bağlantıyla yapılır. Platform yöneticisi için iki aşamalı doğrulama önerilir.
- **Panel:** CSRF koruması ve oturum zaman aşımı.
- **KVKK:** Kayıtta açık rıza alınır. Hesap silmede soft-delete ve anonimleştirme yapılır; numara ve işlem kayıtları denetim için saklanır.
- **Dosya yükleme:** MIME türü ve boyut kontrolü yapılır, görseller yeniden boyutlandırılır, dosya adları rastgele verilir.

---

## 7. Mobil API (v1)

Tüm uç noktalar `/api/v1` altındadır ve JSON döner. 🔒 = Sanctum token gerektirir. Hata yanıtları `{ "message": "...", "errors": {...} }` biçimindedir; doğrulama hataları HTTP 422 döner.

### 7.1. Kimlik

| Metot | Yol | Açıklama |
|---|---|---|
| POST | `/auth/register` | Ad, soyad, telefon, il, ilçe, doğum tarihi, e-posta, şifre, şifre tekrar, KVKK onayı → token + müşteri no |
| POST | `/auth/login` | E-posta + şifre → token |
| POST | `/auth/logout` 🔒 | |
| POST | `/auth/forgot-password` | Sıfırlama e-postası |
| GET/PUT | `/me` 🔒 | Profil (müşteri no ve doğum tarihi salt okunur), ilçe ve görünen ad tercihi güncelleme |
| DELETE | `/me` 🔒 | Hesap silme talebi |

### 7.2. Keşif (token gerektirmez)

| Metot | Yol | Açıklama |
|---|---|---|
| GET | `/cities`, `/cities/{id}/districts`, `/categories` | |
| GET | `/home` | Ana ekran blokları: sponsorlu/öne çıkan, yakındaki (`lat`/`lng` verilirse), en yüksek oran, yeni, kampanyalı |
| GET | `/branches` | Filtre: `city`, `district`, `category`, `min_rate`, `min_score`, `has_campaign`, `q`, `lat`/`lng`. Sıralama: `rate_desc`, `rate_asc`, `popular`, `newest`, `score`, `most_reviewed`, `nearest`. Sayfalı |
| GET | `/branches/{id}` | Bilgiler, saatler, fotoğraflar, oran, koşullar, aktif kampanyalar, marka menüsü, diğer şubeler, puan özeti |
| GET | `/branches/{id}/reviews` | Sıralama: `newest`, `oldest`, `score`. Sayfalı |

### 7.3. Müşteri 🔒

| Metot | Yol | Açıklama |
|---|---|---|
| GET | `/wallets` | Tüm kumbaralar (restoran + şube adı, bakiye) |
| GET | `/wallets/{id}` | Bakiye, toplam kazanılan/kullanılan, koşullar, şube bilgisi |
| GET | `/wallets/{id}/transactions` | İşlem geçmişi (sayfalı; düzeltmeler dahil) |
| GET | `/approvals?status=pending` | Bildirimler ekranı (kullanım ve düzeltme istekleri); yalnızca uygulama açılışında ve Bildirimler sayfasındaki Yenile butonunda çağrılır |
| POST | `/approvals/{id}/approve` | Onay |
| POST | `/approvals/{id}/reject` | Ret |
| GET | `/campaigns` | Kayıtlı ilçedeki aktif kampanyalar; yalnızca uygulama açılışında ve Kampanyalar sayfasındaki Yenile butonunda çağrılır |
| GET/POST/DELETE | `/favorites`, `/favorites/{branch_id}` | |
| GET | `/me/reviews` | Kendi yorumları |
| PUT | `/branches/{id}/review` | Puan (1–10 tam sayı) ve yorum ekleme veya güncelleme |
| POST | `/reviews/{id}/report` | Yorum şikâyeti |

### 7.4. Personel İşlem API'si — `/api/panel/v1`

Şube yöneticisi ve çalışanlar içindir. Kimlik doğrulama Sanctum ile yapılır: API token'ı veya (web paneli yazıldığında) aynı alan adındaki oturum çerezi. Web panelinin işlem ekranı da bu uç noktaları kullanacaktır.

| Metot | Yol | Açıklama |
|---|---|---|
| POST | `/auth/login` | E-posta + şifre → token |
| POST | `/auth/logout` | |
| GET | `/me` | Kullanıcı, şube, yetkiler, aylık sayaç durumu |
| GET | `/customers/{customer_no}` | Ad-soyad + şube bakiyesi + bugün doğum günü mü (dakikada 20 sorgu sınırı) |
| GET | `/customers/{customer_no}/campaigns` | Bu müşteri için uygun oran kampanyaları (doğum günüyse doğum günü kampanyaları dahil) |
| GET | `/customers/{customer_no}/transactions` | Müşterinin bu şubedeki işlem geçmişi (düzeltme için) |
| POST | `/earn/preview` | Harcama + seçilen kampanya → oran, ödül, limit durumu |
| POST | `/earn` | Earn (idempotency_key ile) |
| POST | `/redeem` | Redeem onay isteği oluşturur |
| GET | `/approval-requests/{id}` | İstek durumu (polling) |
| POST | `/approval-requests/{id}/withdraw` | Bekleyen isteği geri çeker |
| POST | `/corrections` | Düzeltme isteği (yalnızca şube yöneticisi) |

---

## 8. Test Stratejisi (kritik alanlar)

- **Sayaç:** Aynı müşteri aynı ayda N işlem yaptığında sayaç 1 artmalı. Ay dönümünde sayaç sıfırdan başlamalı. Limit dolunca yalnızca yeni müşteri reddedilmeli. Pro pakette limit uygulanmamalı. Eş zamanlı iki earn aynı müşteriyi iki kez saymamalı.
- **Kumbara:** Şubeler arası izolasyon korunmalı. Bakiye negatife düşmemeli. Onaylanmamış redeem ve düzeltme bakiyeyi değiştirmemeli. Onay anında bakiye yetersizse istek reddedilmeli. Aynı idempotency anahtarı mükerrer işlem üretmemeli.
- **Oran ve kampanya:** İşlem anındaki oran kullanılmalı. Oran değişikliği geçmişi etkilememeli. Uygun olmayan kampanya seçilememeli; kampanya işlem sırasında sona ererse işlem hata vermeli. Doğum günü kampanyası yalnızca doğum gününde listelenmeli (29 Şubat dahil). Kuruş hesaplaması doğru yapılmalı.
- **Müşteri numarası:** Eş zamanlı kayıtlarda numaralar boşluksuz ve benzersiz olmalı.
- **Yetki:** Her rol yalnızca kendi kapsamındaki verilere erişebilmeli. Çalışan izinleri doğru uygulanmalı.
- **Puanlama:** Earn yapmamış müşteri yorum yazamamalı. Müşteri başına tek değerlendirme olmalı. 10 değerlendirme eşiği uygulanmalı.
