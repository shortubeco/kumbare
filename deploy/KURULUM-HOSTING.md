# Kumbare Backend – cPanel Kurulum Rehberi (SSH olmadan)

Adres: **https://kumbareapi.trthabercanli.com**
Paket: `deploy/kumbare-backend.zip`

SSH olmadığı için Laravel komutları cPanel'in **Cron Jobs** bölümünden **tek seferlik** görevlerle çalıştırılır: görev eklenir, 1–2 dakika beklenir, sonuç dosyası kontrol edilir, görev silinir.

> Aşağıda `KULLANICI` cPanel kullanıcı adınızdır. Ev klasörünüz `/home/KULLANICI` olur (cPanel ana sayfasında sağ tarafta "Home Directory" olarak yazar).

---

## Adım 1 – PHP ayarları

cPanel'de **Select PHP Version** (veya **MultiPHP Manager**) bölümünü açın.

1. PHP sürümünü **8.2** seçin.
2. **Extensions** sekmesinde şunların işaretli olduğundan emin olun:
   `pdo_pgsql`, `pgsql`, `mbstring`, `intl`, `gd`, `fileinfo`, `openssl`, `zip`, `curl`, `tokenizer`, `xml`, `ctype`
3. Kaydedin.

## Adım 2 – Dosyaları yükleme

1. cPanel → **File Manager** → ev klasörünüz (`/home/KULLANICI`). `public_html`'in **içine değil**, onunla aynı seviyeye.
2. **Upload** ile `kumbare-backend.zip` dosyasını yükleyin.
3. Dosyaya sağ tıklayıp **Extract** deyin. `/home/KULLANICI/kumbare-backend` klasörü oluşur.
4. ZIP dosyasını silin.

## Adım 3 – Alt alan adı

cPanel → **Domains** → **Create A New Domain**:

| Alan | Değer |
|---|---|
| Domain | `kumbareapi.trthabercanli.com` |
| Share document root | **İşaretli olmasın** |
| Document Root | `kumbare-backend/public` |

Ardından **SSL/TLS Status** → `kumbareapi.trthabercanli.com` → **Run AutoSSL** (ücretsiz SSL).

## Adım 4 – `.env` dosyasını doldurma

1. File Manager → sağ üstte **Settings** → **Show Hidden Files** işaretleyin.
2. `kumbare-backend/.env` dosyasına sağ tıklayın → **Edit**.
3. `DOLDURUN_` ile başlayan değerleri değiştirin:
   - **DB_DATABASE / DB_USERNAME / DB_PASSWORD**: cPanel → **PostgreSQL Databases** sayfasındaki tabloları kurduğunuz veritabanı ve kullanıcı. cPanel bunların başına kullanıcı adınızı ekler, örn. `KULLANICI_kumbare`. Kullanıcının bu veritabanına yetkili olduğundan emin olun.
   - **MAIL_HOST notu (bu hosting):** `mail.trthabercanli.com` kullanılamaz, çünkü sunucu sertifikası `*.web-hosting.com` adına. Bunun yerine sunucunun kendi adı yazılır. Ad, cron'da `hostname` komutuyla öğrenilir (`serverXXX.web-hosting.com`). Port 465, `MAIL_SCHEME=smtps`. Ayrıntı için cPanel → Server Name.
   - **MAIL_...**: Şifre sıfırlama e-postaları için. cPanel → **Email Accounts** → bir hesap (örn. `noreply@trthabercanli.com`) → **Connect Devices** sayfasındaki SMTP sunucusu ve şifresi. İsterseniz bu kısmı sonra doldurabilirsiniz.
4. Kaydedin. **`APP_KEY` satırına dokunmayın.**

### Adım 4b – Veritabanı kullanıcısına yetki verme

Tabloları phpPgAdmin'de cPanel ana kullanıcısıyla kurduysanız, `.env`'deki uygulama kullanıcısının tablolara ve sayaçlara (sequence) yetkisi olmaz. Bu durumda "permission denied for sequence" hatası alınır.

phpPgAdmin → veritabanı → **SQL** penceresinde çalıştırın. `UYGULAMA_KULLANICISI` yerine `.env`'deki `DB_USERNAME` değerini yazın:

```sql
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO UYGULAMA_KULLANICISI;
GRANT USAGE, SELECT, UPDATE ON ALL SEQUENCES IN SCHEMA public TO UYGULAMA_KULLANICISI;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO UYGULAMA_KULLANICISI;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT, UPDATE ON SEQUENCES TO UYGULAMA_KULLANICISI;
```

## Adım 5 – Komut satırındaki PHP'nin yolunu bulma

Cron görevleri web sitesiyle aynı PHP sürümünü kullanmayabilir. Doğru yolu bulmak için:

1. cPanel → **Cron Jobs** → **Add New Cron Job** → Common Settings: **Once Per Minute**.
2. Command alanına:
   ```
   (php -v; echo ---; ls /opt/cpanel/ | grep ea-php; ls /opt/alt/ | grep php8) > $HOME/php-surum.txt 2>&1
   ```
3. 1–2 dakika bekleyin, File Manager'da `php-surum.txt` dosyasını açın.
4. **Cron görevini silin.**

Sonuç:
- İlk satır `PHP 8.2.x` ise aşağıdaki komutlarda `PHP` yerine sadece `php` yazın.
- Değilse, listede gördüğünüz klasöre göre tam yolu kullanın:
  - `ea-php82` → `/opt/cpanel/ea-php82/root/usr/bin/php`
  - `php82` → `/opt/alt/php82/usr/bin/php`

> Aşağıdaki komutlarda geçen `PHP` kelimesini bu yolla değiştirin.
>
> **Bu hosting için tespit edilen yol:** `/opt/alt/php82/usr/bin/php` (CLI, PHP 8.2.35, `pdo_pgsql` yüklü). Düz `php` komutu burada `cgi-fcgi` türünde açıldığı için kullanılmamalıdır.

## Adım 6 – Tek seferlik kurulum komutları

Yeni bir cron görevi ekleyin (Once Per Minute):

```
cd $HOME/kumbare-backend && (PHP artisan storage:link; PHP artisan config:cache; PHP artisan route:cache; PHP artisan about --only=environment) > $HOME/kumbare-kurulum.txt 2>&1
```

1–2 dakika sonra `kumbare-kurulum.txt` dosyasını açın. "INFO … cached successfully" satırlarını ve `Environment … production` bilgisini görmelisiniz. **Ardından görevi silin.**

> **Önemli:** `.env` dosyasını sonradan değiştirirseniz bu adımı tekrarlamanız gerekir. Laravel ayarları önbellekten okur.

## Adım 7 – Platform yöneticisi hesabı

Yeni bir cron görevi ekleyin (Once Per Minute). E-posta, ad, soyad ve şifreyi kendi bilgilerinizle değiştirin:

```
cd $HOME/kumbare-backend && PHP artisan kumbare:create-admin --email='siz@ornek.com' --first-name='Ad' --last-name='Soyad' --password='EnAz12KarakterliSifre' > $HOME/kumbare-admin.txt 2>&1
```

- Şifre en az 12 karakter olmalı. **`%` ve `'` karakterlerini kullanmayın** (cron bunları özel karakter sayar).
- 1–2 dakika sonra `kumbare-admin.txt` dosyasında "Platform yöneticisi oluşturuldu" yazmalı.
- **Görevi hemen silin** ve `kumbare-admin.txt` dosyasını da silin. Şifre görev listesinde açık yazılı durur.

## Adım 8 – Kalıcı zamanlanmış görev

Bu görev **silinmeyecek**. Kampanya kapanışı, abonelik bitişi, sponsorluk dönemi ve popülerlik puanı bununla çalışır.

Common Settings: **Once Per Five Minutes** (bu hosting en sık 5 dakikaya izin veriyor), Command:

```
cd $HOME/kumbare-backend && PHP artisan schedule:run >> /dev/null 2>&1
```

> Zamanlanmış görevlerin saatleri 5'in katı olan dakikalara göre ayarlanmıştır (`routes/console.php`).

## Adım 9 – Test

Tarayıcıda açın:

| Adres | Beklenen |
|---|---|
| https://kumbareapi.trthabercanli.com/up | Yeşil "Application up" sayfası |
| https://kumbareapi.trthabercanli.com/api/v1/cities | 81 ilin listesi (JSON) |
| https://kumbareapi.trthabercanli.com/api/v1/branches | Boş liste: `"data": []` (henüz restoran yok) |
| https://kumbareapi.trthabercanli.com/.env | **Açılmamalı** (403 veya 404) |

---

## Sorun giderme

| Belirti | Çözüm |
|---|---|
| 500 hatası | `kumbare-backend/storage/logs/` içindeki en yeni `laravel-….log` dosyasına bakın |
| "could not find driver" | Adım 1'de `pdo_pgsql` açık değil |
| Veritabanı bağlantı hatası | `.env` bilgilerini kontrol edin, sonra **Adım 6'yı tekrarlayın** |
| `storage` ile ilgili izin hatası | File Manager → `storage` ve `bootstrap/cache` klasörlerine sağ tık → **Change Permissions** → 755 (alt klasörlerle birlikte) |
| `/up` yerine klasör listesi veya 404 | Adım 3'teki Document Root `kumbare-backend/public` olmalı |

## Web sitesi (kumbare.trthabercanli.com)

Web sitesi aynı uygulamada çalışır; ikinci bir alan adı aynı klasöre bağlanır.

1. cPanel → **Domains** → **Create A New Domain**: `kumbare.trthabercanli.com`, Share document root işaretsiz, Document Root: `kumbare-backend/public`.
2. `.env` dosyasında:
   ```
   APP_URL=https://kumbare.trthabercanli.com
   WEB_DOMAIN=kumbare.trthabercanli.com
   ```
   `APP_URL` şifre sıfırlama bağlantılarının adresidir; web sitesini göstermelidir.
   `WEB_DOMAIN` tanımlıyken site yalnızca bu adreste açılır; API adresinde ana sayfa yerine API bilgisi döner ve arama motorları API adresini taramaz.
3. Sunucuda `kumbare-backend/public/robots.txt` dosyası varsa silinmelidir (yerine uygulama kendi robots.txt'sini üretir).
4. Önbellekleri yenileyin (tek seferlik cron):
   ```
   cd $HOME/kumbare-backend && (/opt/alt/php82/usr/bin/php artisan config:cache; /opt/alt/php82/usr/bin/php artisan route:cache; /opt/alt/php82/usr/bin/php artisan view:cache) > $HOME/kumbare-kurulum.txt 2>&1
   ```

## Güncelleme (yeni sürüm yüklerken)

### Tam paket (`kumbare-backend.zip`)

1. Yeni ZIP'i yükleyip **`.env` dosyası hariç** dosyaların üzerine açın. Yeni paketteki `.env`'i silin, mevcut `.env`'iniz kalsın.
2. Adım 6'yı tekrarlayın.

### Küçük güncelleme paketleri (`guncelleme-N-....zip`)

Bu paketlerde yalnızca değişen dosyalar, `kumbare-backend/...` klasör yapısıyla bulunur; `.env` içermez.

1. cPanel → **File Manager** → ev klasörü (`/home/sozcpdjd`) → **Upload** ile ZIP'i yükleyin.
2. ZIP'e sağ tık → **Extract** → hedef: `/home/sozcpdjd` → üzerine yazmayı onaylayın.
3. Pakette **rota** değiştiyse (yeni sayfa/adres eklendiyse) önbelleği yenileyin — cPanel → **Cron Jobs** → **Once Per Five Minutes**:
   ```
   cd /home/sozcpdjd/kumbare-backend && /opt/alt/php82/usr/bin/php artisan route:cache > /home/sozcpdjd/guncelleme.txt 2>&1 && /opt/alt/php82/usr/bin/php artisan view:cache >> /home/sozcpdjd/guncelleme.txt 2>&1
   ```
   5 dakika içinde `guncelleme.txt` oluşur; içinde iki "successfully" satırı görününce görevi **silin**. `.env` değiştiyse komutun başına `config:cache` da eklenir.
   Yalnızca görünüm, CSS/JS veya denetleyici değiştiyse cron gerekmez (görünümler kendiliğinden yenilenir); tarayıcıda Ctrl+F5 yeterlidir.
4. Komutta `KULLANICI` gibi bir yer tutucu kalmamalı; ev klasörü yolu `/home/sozcpdjd`'dir.

| Paket | İçerik | Cron |
|---|---|---|
| guncelleme-5-platform-yonetimi | Panel girişi + platform yönetimi `/yonetim` | route + view |
| guncelleme-6-musteri-arama | (7'ye dahil) | — |
| guncelleme-7-basvuru-formu | Başvuru formu bot tuzağı + müşteri arama (8'e dahil) | — |
| guncelleme-8-grafikler | Platform genel bakış grafikleri | — |
| guncelleme-9-genel-bakis-duzen | Genel bakış kutu düzeni | — |
| guncelleme-10-isletme-grafikleri | İşletme grafikleri | — |
| guncelleme-11-paket-odeme | Şube: Paket ve ödeme, Sponsorluk, Havale bildirimi | route + view |
| guncelleme-12-hata-mesajlari | Web formu hata mesajları büyük harfle | — |
| guncelleme-13-islem-ekrani | Şube: İşlem ekranı | route + view |
| guncelleme-14-sube-genel-bakis | Şube: Genel bakış | route + view |
| guncelleme-15-musteriler-duzeltmeler | Şube: Müşteriler, Düzeltmeler | route + view |
| guncelleme-25-mobil-menu | Site: telefonda ☰ ile soldan kayan menü (kaydırma çubuğu kalktı) | — |
| guncelleme-24-musteri-yonlendirme | Site: "Müşteriler için" menü düğmesi, ana sayfada üyelik kutusu ve bant etiketleri | — |
| guncelleme-23-android-1.0.1 | Android APK 1.0.1 (23 MB) + `android.json` | — |
| guncelleme-22-android-apk | Android APK 1.0.0 + `/mobil-uygulama` indirme kartı + `/indir` klasörü; manifest dosya türü | — |
| guncelleme-21-mobil-uygulama | iPhone/Android web uygulaması `/uygulama` (PWA) + site sayfası `/mobil-uygulama`; ana sayfa ve menüde bağlantılar | route + view |
| guncelleme-20-menu-kategori-silme | Menü: ürünü silinmiş kategori artık silinebiliyor | — |
| guncelleme-19-isletme-paneli | İşletme yöneticisi paneli (genel bakış, şubeler, yöneticiler, menü, raporlar, abonelikler, bilgiler) | route + view |
| guncelleme-18-sube-raporlari | Şube: Raporlar (+ 17'deki 403 sayfası) | route + view |
| guncelleme-17-erisim-sayfasi | Yetkisiz sayfa (403) ekranı: hesap bilgisi + çıkış | — |
| guncelleme-16-sube-ayarlari | Şube: Ödül oranı, Kampanyalar, Çalışanlar, Şube bilgileri/fotoğraflar, Değerlendirmeler, Bildirimler | **config** + route + view |

## Panel adresleri

| Kim | Adres |
|---|---|
| Tüm panel kullanıcıları (giriş) | https://kumbare.trthabercanli.com/panel/giris |
| Platform yöneticisi | https://kumbare.trthabercanli.com/yonetim |
| İşletme (marka) yöneticisi | https://kumbare.trthabercanli.com/panel/isletme |
| Şube yöneticisi / çalışan | https://kumbare.trthabercanli.com/panel/islem (yönetici ayrıca `/panel/genel-bakis`, `/panel/paket`, `/panel/sponsorluk`) |

Şube fotoğrafları ve kampanya görselleri `kumbare-backend/public/uploads/` altına yazılır (herkese açık). Bu klasör güncelleme paketlerinde yoktur; silinmemeli ve üzerine yazılmamalıdır.

Havale dekontları sunucuda `kumbare-backend/storage/app/private/dekontlar/` altında tutulur; bu klasör dışarıdan açılamaz. `storage` klasörünün yazma izni (755) bozulursa dekont yüklenemez.

## Güvenlik notları

- `.env` dosyası veritabanı şifresini ve uygulama anahtarını (`APP_KEY`) içerir. Kimseyle paylaşmayın.
- `APP_KEY` değişirse oturumlar ve şifreli veriler geçersiz olur. Bir kez kurduktan sonra değiştirmeyin.
- `deploy/kumbare-backend.zip` de bu anahtarı içerir. Kurulumdan sonra bilgisayarınızda güvenli tutun veya silin.


## Web uygulaması (PWA) güncelleme

Müşteri uygulamasının iPhone/web sürümü `kumbare-backend/public/uygulama` klasöründedir (derleme çıktısı, depoya girmez).

1. Bilgisayarda `mobile` klasöründe `node scripts/build-web.mjs` çalıştırılır: canlı API adresiyle derler ve `backend/public/uygulama` klasörüne kopyalar (giriş dosyası `app.html`; klasördeki `.htaccess` `DirectoryIndex app.html` ve manifest dosya türünü içerir).
2. `public/uygulama` klasörünün tamamı `kumbare-backend/public/uygulama` yoluyla ZIP'lenir; File Manager'da ev klasörüne Extract edilir. Eski karma adlı JS dosyaları sunucuda kalabilir, zararsızdır.
3. Yalnızca uygulama değiştiyse cron gerekmez. `/uygulama/...` alt adreslerini `Web\AppController` karşılar (`app.html` döner).

## Android uygulaması (APK) yeni sürüm yayımlama

Android uygulaması siteden indirilir: `kumbare-backend/public/indir/kumbare.apk`. Aynı klasördeki `android.json` en son sürümü tanımlar; telefondaki uygulama açılışta bu dosyaya bakar ve kendi sürümünden yeniyse "Yeni sürüm var" kutusunu gösterir.

1. `mobile/app.json` içinde `version` (ör. 1.0.2) ve `android.versionCode` (bir artır) güncellenir.
2. Derleme Expo bulutunda (EAS) yapılır; bilgisayardaki PowerShell'de:
   ```
   cd C:\claude-lessons\kumbare.com\mobile
   npx eas-cli@latest build -p android --profile apk
   ```
   Derleme kuyrukla birlikte 20–50 dakika sürebilir. Bitince ekranda APK bağlantısı çıkar.
3. APK `kumbare.apk` adıyla `kumbare-backend/public/indir/` yoluna konur; `android.json` içinde `version_code`, `version_name` ve isteğe bağlı `notes` güncellenir. İkisi birlikte ZIP'lenip ev klasörüne Extract edilir. **Cron gerekmez.**
4. Kontrol: `/mobil-uygulama` sayfasında yeni sürüm ve boyut görünmeli; eski sürümü kullanan telefonda uygulama tamamen kapatılıp açılınca "Yeni sürüm var" çıkmalı.

**İmza anahtarı:** Bütün sürümler aynı anahtarla imzalanmalıdır; anahtar Expo hesabında (`hakanegne`) saklanır. Yedeği `npx eas-cli@latest credentials -p android` → `apk` profili → Keystore → "Download existing keystore" ile indirilip güvenli bir yerde tutulmalıdır. Anahtar kaybolursa telefonlara kurulu uygulama güncellenemez; kullanıcıların silip yeniden kurması gerekir.
