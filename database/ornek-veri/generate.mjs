// Kumbare örnek veri üreticisi
// Kullanım: node generate.mjs   (çıktı: bu klasörde 01-...sql, 02-...sql ...)
//
// - 81 ilde 100'er restoran (şube); bir kısmı zincir markaların şubeleri
// - Her işletmeye işletme yöneticisi, her şubeye şube yöneticisi
// - 2000 müşteri, kumbaralar, ödül kazanma/kullanma işlemleri, yorumlar
// - Kampanyalar, sponsorluklar, Profesyonel abonelikler, favoriler
// Tüm örnek hesapların şifresi: Kumbare2026!
// Üretim deterministiktir (sabit tohum); aynı çıktıyı tekrar üretir.

import { readFileSync, writeFileSync, readdirSync, unlinkSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const IL_ILCE = join(HERE, '..', 'kumbare-il-ilce.sql');

// ---------------------------------------------------------------- ayarlar
const BRANCHES_PER_CITY = 100;
const CUSTOMER_COUNT = 2000;
const NOW = new Date('2026-11-11T12:00:00+03:00');
const PASSWORD_HASH = '$2y$10$fFbX0.6YC6ZjItiNWqrJ7.2OyMLFMRcHqEMiobNL3QuZIJAtwFpRO'; // Kumbare2026!
const PANEL_ID_OFFSET = 100000; // canlıdaki platform yöneticisiyle çakışmasın
const MAX_FILE_BYTES = 3.5 * 1024 * 1024;
const BATCH = 300;

// ---------------------------------------------------------------- rastgelelik
let seed = 20261111;
function rand() {
  seed |= 0; seed = (seed + 0x6d2b79f5) | 0;
  let t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
  t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
  return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
}
const int = (a, b) => a + Math.floor(rand() * (b - a + 1));
const pick = (arr) => arr[Math.floor(rand() * arr.length)];
const chance = (p) => rand() < p;
function weighted(items) { // [[value, weight], ...]
  const total = items.reduce((s, [, w]) => s + w, 0);
  let r = rand() * total;
  for (const [v, w] of items) { if ((r -= w) <= 0) return v; }
  return items[items.length - 1][0];
}
function shuffle(arr) {
  const a = [...arr];
  for (let i = a.length - 1; i > 0; i--) { const j = Math.floor(rand() * (i + 1)); [a[i], a[j]] = [a[j], a[i]]; }
  return a;
}

// ---------------------------------------------------------------- yardımcılar
const TR = { ç: 'c', Ç: 'c', ğ: 'g', Ğ: 'g', ı: 'i', I: 'i', İ: 'i', ö: 'o', Ö: 'o', ş: 's', Ş: 's', ü: 'u', Ü: 'u', â: 'a', î: 'i', û: 'u' };
const slugify = (s) => [...s].map((c) => TR[c] ?? c).join('').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
const q = (s) => (s === null || s === undefined ? 'NULL' : "'" + String(s).replace(/'/g, "''") + "'");
const money = (cents) => (cents < 0 ? '-' : '') + Math.floor(Math.abs(cents) / 100) + '.' + String(Math.abs(cents) % 100).padStart(2, '0');
const pad = (n) => String(n).padStart(2, '0');
function ts(d) { // Türkiye saati (+03)
  const t = new Date(d.getTime() + 3 * 3600 * 1000);
  return `'${t.getUTCFullYear()}-${pad(t.getUTCMonth() + 1)}-${pad(t.getUTCDate())} ${pad(t.getUTCHours())}:${pad(t.getUTCMinutes())}:${pad(t.getUTCSeconds())}+03'`;
}
function periodOf(d) {
  const t = new Date(d.getTime() + 3 * 3600 * 1000);
  return `${t.getUTCFullYear()}-${pad(t.getUTCMonth() + 1)}-01`;
}
const daysAgo = (n) => new Date(NOW.getTime() - n * 86400000);
const between = (a, b) => (a >= b ? new Date(b.getTime()) : new Date(a.getTime() + rand() * (b.getTime() - a.getTime())));
const addMinutes = (d, m) => new Date(d.getTime() + m * 60000);
const pctOf = (cents, rateBasis) => Math.floor((cents * rateBasis + 5000) / 10000); // normal yuvarlama

// ---------------------------------------------------------------- il / ilçe
const ilIlce = readFileSync(IL_ILCE, 'utf8');
const cities = []; const districtsByCity = {};
let districtId = 0; let districtChecksum = 0n;
for (const line of ilIlce.split('\n')) {
  let m = line.match(/^\s+\((\d+), '([^']+)', '([^']+)', (\d+)\)/);
  if (m) { cities.push({ id: +m[1], name: m[2], slug: m[3] }); continue; }
  m = line.match(/^\s+\((\d+), '((?:[^']|'')+)', '([^']+)'\)/);
  if (m) {
    districtId++;
    const d = { id: districtId, cityId: +m[1], name: m[2].replace(/''/g, "'"), slug: m[3] };
    (districtsByCity[d.cityId] ??= []).push(d);
    districtChecksum += BigInt(d.id) * BigInt(d.cityId);
  }
}
if (cities.length !== 81 || districtId !== 973) throw new Error(`il/ilçe okunamadı: ${cities.length}/${districtId}`);
const cityById = Object.fromEntries(cities.map((c) => [c.id, c]));

// Yaklaşık il merkezi koordinatları (plaka sırasıyla)
const COORDS = [null,
  [37.00, 35.32], [37.76, 38.28], [38.76, 30.54], [39.72, 43.05], [40.65, 35.83], [39.93, 32.86], [36.89, 30.71], [41.18, 41.82], [37.85, 27.85], [39.65, 27.88],
  [40.14, 29.98], [38.88, 40.50], [38.40, 42.11], [40.74, 31.61], [37.72, 30.29], [40.19, 29.06], [40.15, 26.41], [40.60, 33.62], [40.55, 34.95], [37.78, 29.09],
  [37.91, 40.23], [41.68, 26.56], [38.68, 39.22], [39.75, 39.49], [39.90, 41.27], [39.78, 30.52], [37.07, 37.38], [40.91, 38.39], [40.46, 39.48], [37.58, 43.74],
  [36.20, 36.16], [37.76, 30.55], [36.81, 34.64], [41.01, 28.98], [38.42, 27.14], [40.60, 43.10], [41.38, 33.78], [38.72, 35.49], [41.74, 27.23], [39.15, 34.17],
  [40.77, 29.94], [37.87, 32.48], [39.42, 29.98], [38.35, 38.31], [38.61, 27.43], [37.58, 36.94], [37.31, 40.74], [37.22, 28.36], [38.75, 41.51], [38.62, 34.71],
  [37.97, 34.68], [40.98, 37.88], [41.02, 40.52], [40.78, 30.40], [41.29, 36.33], [37.93, 41.94], [42.03, 35.15], [39.75, 37.02], [40.98, 27.51], [40.31, 36.55],
  [41.00, 39.72], [39.11, 39.55], [37.17, 38.79], [38.68, 29.41], [38.50, 43.38], [39.82, 34.81], [41.45, 31.79], [38.37, 34.03], [40.26, 40.23], [37.18, 33.22],
  [39.85, 33.51], [37.88, 41.13], [37.52, 42.46], [41.63, 32.34], [41.11, 42.70], [39.92, 44.04], [40.66, 29.27], [41.20, 32.62], [36.72, 37.12], [37.07, 36.25],
  [40.84, 31.16]];

// ---------------------------------------------------------------- kategoriler ve menüler
// [ad, işletme türü eki, açılış, kapanış, [ [menü kategorisi, [[ürün, açıklama, min TL, max TL], ...]], ... ] ]
const CATEGORIES = [
  ['Kebap', 'Kebap', '11:00', '23:00', [
    ['Kebaplar', [['Adana Kebap', 'Zırhla çekilmiş acılı kıyma, közde biber ve domates ile', 280, 420], ['Urfa Kebap', 'Acısız kıyma kebabı, lavaş ve sumaklı soğan ile', 270, 400], ['Beyti', 'Lavaşa sarılı kebap, yoğurt ve tereyağlı sos ile', 320, 460], ['Patlıcan Kebap', 'Közde patlıcan ve kıyma şiş', 300, 440], ['Kuzu Şiş', 'Marine edilmiş kuzu kuşbaşı', 360, 520]]],
    ['Başlangıçlar', [['Mercimek Çorbası', 'Günlük taze çorba', 70, 120], ['Çiğ Köfte', 'Marul ve nar ekşisi ile', 80, 130], ['Ezme Salata', 'Acılı domates ezmesi', 60, 100], ['Humus', 'Tereyağlı pastırmalı', 90, 150]]],
    ['İçecekler', [['Ayran', 'Yayık ayranı', 30, 50], ['Şalgam', 'Acılı veya acısız', 35, 55], ['Künefe', 'Antep fıstıklı', 120, 180]]]]],
  ['Pide & Lahmacun', 'Pide Salonu', '10:00', '23:00', [
    ['Pideler', [['Kıymalı Pide', 'Taş fırında', 160, 240], ['Kuşbaşılı Pide', 'Dana kuşbaşı ile', 200, 290], ['Kaşarlı Pide', 'Bol kaşarlı', 150, 220], ['Karışık Pide', 'Sucuk, kaşar, kıyma', 210, 300]]],
    ['Lahmacun', [['Lahmacun', 'İnce hamur, bol yeşillik ile', 60, 110], ['Acılı Lahmacun', 'Antep usulü', 65, 115], ['Fındık Lahmacun', '3 adet küçük boy', 90, 140]]],
    ['İçecekler', [['Ayran', 'Açık ayran', 25, 45], ['Kola', '330 ml', 40, 60]]]]],
  ['Ev Yemekleri', 'Lokanta', '07:30', '20:00', [
    ['Ana Yemekler', [['Kuru Fasulye', 'Tereyağlı, pilav ile', 120, 180], ['Etli Nohut', 'Kuzu etli', 130, 190], ['İzmir Köfte', 'Fırında patatesli', 150, 220], ['Tavuk Sote', 'Sebzeli', 140, 200], ['Karnıyarık', 'Kıymalı patlıcan', 150, 210]]],
    ['Çorbalar', [['Mercimek Çorbası', 'Limon ve ekmek ile', 60, 100], ['Ezogelin Çorbası', 'Nane ve pul biberli', 60, 100], ['Tarhana Çorbası', 'Ev yapımı tarhana', 65, 105]]],
    ['Tatlılar', [['Sütlaç', 'Fırın sütlaç', 70, 110], ['Kazandibi', 'Tarçınlı', 75, 115]]]]],
  ['Balık', 'Balık Evi', '12:00', '00:00', [
    ['Balıklar', [['Levrek Izgara', 'Roka ve soğan ile', 380, 560], ['Çupra Izgara', 'Mevsim salatası ile', 380, 560], ['Hamsi Tava', 'Mevsiminde', 220, 340], ['Somon Izgara', 'Sebzeli', 420, 620]]],
    ['Mezeler', [['Atom', 'Acılı yoğurt mezesi', 80, 130], ['Deniz Börülcesi', 'Zeytinyağlı', 90, 140], ['Kalamar Tava', 'Tarator sos ile', 260, 380], ['Karides Güveç', 'Kaşarlı', 280, 420]]]]],
  ['Burger', 'Burger', '11:00', '00:00', [
    ['Burgerler', [['Klasik Burger', '150 gr dana köfte, cheddar, turşu', 220, 320], ['Cheeseburger', 'Çift cheddar', 240, 340], ['Mantarlı Burger', 'Sote mantar ve İsviçre peyniri', 260, 360], ['Tavuk Burger', 'Çıtır tavuk göğsü', 200, 290], ['Double Burger', '2 × 120 gr köfte', 320, 440]]],
    ['Yan Ürünler', [['Patates Kızartması', 'Baharatlı', 70, 120], ['Soğan Halkası', '8 adet', 80, 130], ['Milkshake', 'Çikolata, çilek veya vanilya', 110, 170]]]]],
  ['Pizza', 'Pizza', '11:00', '00:00', [
    ['Pizzalar', [['Margarita', 'Domates sos, mozzarella, fesleğen', 220, 320], ['Karışık Pizza', 'Sucuk, salam, mantar, biber', 270, 380], ['Sucuklu Pizza', 'Bol sucuklu', 250, 350], ['Ton Balıklı Pizza', 'Mısır ve soğan ile', 270, 380], ['Vejetaryen Pizza', 'Mevsim sebzeleri', 240, 340]]],
    ['Ara Sıcaklar', [['Sarımsaklı Ekmek', 'Kaşarlı', 80, 130], ['Tavuk Kanat', '6 adet', 150, 220], ['Sezar Salata', 'Tavuklu', 160, 230]]]]],
  ['Kahvaltı', 'Kahvaltı Evi', '07:00', '16:00', [
    ['Kahvaltılar', [['Serpme Kahvaltı (2 kişilik)', 'Peynir çeşitleri, bal, kaymak, reçel, yumurta', 650, 950], ['Kahvaltı Tabağı', 'Tek kişilik', 260, 380], ['Menemen', 'Domatesli biberli', 140, 210], ['Sucuklu Yumurta', 'Sahanda', 160, 230]]],
    ['Hamur İşleri', [['Gözleme', 'Peynirli veya patatesli', 120, 180], ['Pişi', '5 adet', 90, 140], ['Simit', 'Taze', 20, 35]]],
    ['İçecekler', [['Çay', 'Demlik çay (bardak)', 20, 35], ['Türk Kahvesi', 'Lokum ile', 60, 100], ['Taze Portakal Suyu', 'Sıkma', 80, 130]]]]],
  ['Döner', 'Döner', '10:00', '23:00', [
    ['Dönerler', [['Et Döner Dürüm', 'Lavaşta', 180, 270], ['Tavuk Döner Dürüm', 'Lavaşta', 120, 180], ['İskender', 'Tereyağı ve yoğurt ile', 320, 460], ['Porsiyon Et Döner', 'Pilav ile', 260, 380]]],
    ['Ekmek Arası', [['Yarım Ekmek Tavuk Döner', 'Patates ve turşu ile', 110, 160], ['Yarım Ekmek Et Döner', 'Domates ve soğan ile', 170, 250]]],
    ['İçecekler', [['Ayran', 'Şişe', 25, 45], ['Şalgam', 'Acılı', 30, 50]]]]],
  ['Tatlı & Pastane', 'Pastanesi', '08:00', '23:00', [
    ['Şerbetli Tatlılar', [['Fıstıklı Baklava (porsiyon)', '4 dilim', 180, 280], ['Künefe', 'Hatay peyniri ile', 140, 210], ['Şöbiyet', 'Kaymaklı', 170, 260]]],
    ['Pastalar', [['Çikolatalı Pasta (dilim)', 'Belçika çikolatası', 110, 170], ['Cheesecake', 'Frambuazlı', 130, 190], ['Profiterol', 'Çikolata soslu', 110, 160]]],
    ['İçecekler', [['Çay', 'İnce belli', 20, 35], ['Türk Kahvesi', 'Lokum ile', 60, 100]]]]],
  ['Kafe', 'Kafe', '08:00', '00:00', [
    ['Kahveler', [['Latte', 'Sıcak veya buzlu', 90, 140], ['Americano', 'Çift shot', 80, 120], ['Türk Kahvesi', 'Lokum ile', 60, 100], ['Filtre Kahve', 'Günün çekirdeği', 80, 120]]],
    ['Atıştırmalıklar', [['Tost', 'Kaşarlı veya karışık', 110, 170], ['Brownie', 'Dondurma ile', 110, 160], ['Kruvasan', 'Tereyağlı', 80, 120], ['Sandviç', 'Tavuklu', 140, 200]]]]],
  ['Mantı', 'Mantı Evi', '10:00', '22:00', [
    ['Mantılar', [['Kayseri Mantısı', 'Yoğurt ve tereyağlı sos ile', 180, 260], ['Kıymalı Çiğ Börek', '2 adet', 120, 180], ['Fırın Mantı', 'Kaşarlı', 200, 280], ['Sinop Mantısı', 'Cevizli', 190, 270]]],
    ['Ev Yemekleri', [['Yaprak Sarma', 'Zeytinyağlı', 120, 180], ['Su Böreği', 'Peynirli', 110, 170], ['Ayran', 'Ev yapımı', 25, 45]]]]],
  ['Köfte', 'Köftecisi', '10:00', '23:00', [
    ['Köfteler', [['Izgara Köfte', 'Piyaz ve közde biber ile', 220, 320], ['Kaşarlı Köfte', 'İçi kaşarlı', 250, 350], ['Kasap Köfte', 'Günlük çekilmiş', 240, 340], ['Ekmek Arası Köfte', 'Yarım ekmek', 140, 210]]],
    ['Yanında', [['Piyaz', 'Antalya usulü', 70, 110], ['Çoban Salata', 'Mevsim', 60, 100], ['Ayran', 'Yayık', 25, 45]]]]],
  ['Çorba', 'Çorba Evi', '00:00', '23:59', [
    ['Çorbalar', [['İşkembe Çorbası', 'Sarımsaklı sirke ile', 130, 200], ['Kelle Paça', 'Tereyağlı', 150, 220], ['Mercimek Çorbası', 'Limonlu', 70, 110], ['Tavuk Suyu Çorba', 'Şehriyeli', 80, 120], ['Yayla Çorbası', 'Naneli', 75, 115]]],
    ['Tatlılar', [['Sütlaç', 'Fırın', 70, 110], ['Tavuk Göğsü', 'Tarçınlı', 80, 120]]]]],
  ['Vegan', 'Vegan Mutfak', '10:00', '22:00', [
    ['Ana Yemekler', [['Nohut Köftesi', 'Humus ve salata ile', 190, 270], ['Sebzeli Buddha Bowl', 'Kinoa, avokado, nohut', 220, 310], ['Vegan Burger', 'Mercimek köftesi', 220, 300], ['Mantar Risotto', 'Hindistan cevizi sütü ile', 230, 320]]],
    ['İçecekler', [['Detoks Smoothie', 'Ispanak, elma, zencefil', 110, 160], ['Limonata', 'Ev yapımı', 70, 110], ['Vegan Cheesecake', 'Kaju bazlı', 140, 200]]]]],
  ['Uzakdoğu', 'Noodle Bar', '12:00', '23:00', [
    ['Ana Yemekler', [['Tavuklu Noodle', 'Sebzeli, soya soslu', 220, 310], ['Sushi Seti (12 parça)', 'Karışık', 380, 560], ['Kung Pao Tavuk', 'Fıstıklı, acılı', 250, 350], ['Teriyaki Somon', 'Pilav ile', 380, 540]]],
    ['Başlangıçlar', [['Bahar Rulosu', '4 adet', 110, 170], ['Edamame', 'Deniz tuzlu', 90, 140], ['Miso Çorbası', 'Tofulu', 90, 130]]]]],
  ['Steakhouse', 'Steakhouse', '12:00', '00:00', [
    ['Etler', [['Dana Antrikot (250 gr)', 'Közde sebze ile', 650, 950], ['Bonfile (200 gr)', 'Patates püresi ile', 750, 1100], ['Kuzu Pirzola', '4 adet', 600, 900], ['Izgara Köfte', 'Kasap köfte', 320, 450]]],
    ['Yan Ürünler', [['Mevsim Salata', 'Nar ekşili', 120, 180], ['Fırın Patates', 'Baharatlı', 100, 150], ['Tiramisu', 'Ev yapımı', 160, 230]]]]],
];

const STREETS = ['Atatürk Cad.', 'Cumhuriyet Cad.', 'İstiklal Cad.', 'Gazi Bulvarı', 'İnönü Cad.', 'Fevzi Çakmak Sok.', 'Mimar Sinan Cad.', 'Hürriyet Cad.', 'Çarşı Cad.', 'Lale Sok.', 'Menekşe Sok.', 'Barış Cad.', 'Zafer Sok.', 'Kordon Boyu', 'Yıldırım Beyazıt Cad.', 'Fatih Sultan Mehmet Cad.', 'Mevlana Cad.', 'Millet Cad.', 'Belediye Sok.', 'Okul Sok.', 'Çınar Sok.', 'Gül Sok.', 'Bahar Cad.', 'Sahil Yolu'];
const PREFIXES = ['Lezzet', 'Sofra', 'Bereket', 'Asmalı', 'Köşk', 'Saray', 'Yıldız', 'Gül', 'Çınar', 'Nar', 'Bahçe', 'Liman', 'Konak', 'Paşa', 'Sultan', 'Anadolu', 'Ege', 'Karadeniz', 'Toros', 'Dicle', 'Fırat', 'Meşhur', 'Tarihi', 'Yeni', 'Güneş', 'Dost', 'Keyif', 'Mutfak', 'Ocakbaşı', 'Yeşil', 'Mavi', 'Altın', 'Ada', 'Köy', 'Bağ', 'Çeşme', 'Tepe', 'Vadi', 'Kale', 'Han'];
const OWNER_NAMES = ['Mehmet', 'Hasan', 'Ali', 'Hüseyin', 'Mustafa', 'Ahmet', 'İbrahim', 'Osman', 'Halil', 'Yusuf', 'Ramazan', 'Murat', 'Kemal', 'Recep', 'Salih', 'Fatma', 'Ayşe', 'Emine', 'Hatice', 'Zeynep'];
const FIRST_M = ['Ahmet', 'Mehmet', 'Mustafa', 'Ali', 'Hüseyin', 'Hasan', 'İbrahim', 'Murat', 'Ömer', 'Emre', 'Burak', 'Can', 'Kerem', 'Eren', 'Yusuf', 'Berk', 'Arda', 'Kaan', 'Onur', 'Serkan', 'Volkan', 'Tolga', 'Barış', 'Cem', 'Deniz', 'Furkan', 'Gökhan', 'Halil', 'Oğuz', 'Uğur'];
const FIRST_F = ['Ayşe', 'Fatma', 'Emine', 'Zeynep', 'Elif', 'Merve', 'Esra', 'Büşra', 'Selin', 'Ece', 'Derya', 'Gamze', 'Seda', 'Tuğba', 'Özlem', 'Hande', 'Burcu', 'Ceren', 'Damla', 'Ebru', 'Gizem', 'İrem', 'Melis', 'Nur', 'Pınar', 'Sevgi', 'Şule', 'Yasemin', 'Dilek', 'Aslı'];
const LAST = ['Yılmaz', 'Kaya', 'Demir', 'Şahin', 'Çelik', 'Yıldız', 'Yıldırım', 'Öztürk', 'Aydın', 'Özdemir', 'Arslan', 'Doğan', 'Kılıç', 'Aslan', 'Çetin', 'Kara', 'Koç', 'Kurt', 'Özkan', 'Şimşek', 'Polat', 'Korkmaz', 'Erdoğan', 'Güneş', 'Aksoy', 'Tekin', 'Bulut', 'Acar', 'Uçar', 'Bozkurt', 'Ateş', 'Karaca', 'Turan', 'Güler', 'Sarı', 'Ekinci', 'Keskin', 'Avcı', 'Tunç', 'Duman'];

const CHAINS = [
  ['Közbaşı Kebap', 0], ['Pidemiz', 1], ['Anne Eli Lokanta', 2], ['Burgerhane', 4], ['Dilim Pizza', 5], ['Kahvaltı Bahçesi', 6],
  ['Döner Durağı', 7], ['Tatlıcı Nine', 8], ['Kahve Molası', 9], ['Mantı Sofrası', 10], ['Köfte Dünyası', 11], ['Çorbacı Dede', 12],
  ['Balıkçı Barınağı', 3], ['Yeşil Tabak', 13], ['Wok Durağı', 14], ['Et Ateşi Steakhouse', 15], ['Lahmacun Ustası', 1], ['Gözleme Köyü', 6],
  ['Kebapçı Halil Usta', 0], ['Simitçi Köşesi', 9],
];

const RATE_WEIGHTS = [[0, 7], [250, 3], [300, 6], [500, 18], [700, 10], [800, 6], [1000, 22], [1200, 8], [1500, 12], [2000, 6], [2500, 2]]; // yüzde × 100
const COMMENTS = {
  high: ['Her şey çok lezzetliydi, kesinlikle tekrar geleceğim.', 'Servis hızlı, personel çok güler yüzlü.', 'Fiyat performans olarak şehrin en iyisi.', 'Porsiyonlar doyurucu, ortam çok temiz.', 'Ailecek geldik, herkes çok memnun kaldı.', 'Kumbaramda biriken ödülle bir öğün bedavaya geldi, harika!', 'Tadı yıllardır hiç değişmedi, ellerinize sağlık.', 'Mekan çok şık, yemekler sıcak geldi.'],
  mid: ['Yemekler güzeldi ama biraz bekledik.', 'Lezzet iyi, fiyatlar biraz yüksek.', 'Ortalama bir deneyimdi, kötü değil.', 'Porsiyon küçüktü ama tadı güzeldi.', 'Kalabalık saatlerde servis yavaşlıyor.'],
  low: ['Siparişim soğuk geldi, beklentimi karşılamadı.', 'Çok uzun bekledik, ilgilenen olmadı.', 'Fiyatına göre lezzet zayıftı.', 'Hijyen konusunda daha dikkatli olunmalı.'],
};
const REPLIES = ['Güzel yorumunuz için çok teşekkür ederiz, sizi tekrar ağırlamaktan mutluluk duyarız.', 'Değerli geri bildiriminiz için teşekkürler. Yaşadığınız aksaklık için özür dileriz, gerekli düzenlemeyi yaptık.', 'Bizi tercih ettiğiniz için teşekkür ederiz, afiyet olsun!'];
const ANNOUNCEMENTS = [['Öğle menüsünde %15 indirim', 'Hafta içi 12:00–15:00 arası tüm öğle menülerinde geçerlidir.'], ['2 kişilik menüde tatlı ikramı', 'İki kişilik menü siparişlerinde günün tatlısı bizden.'], ['Öğrencilere %10 indirim', 'Geçerli öğrenci belgesi ile.'], ['Hafta sonu çocuk menüsü ücretsiz', 'Yetişkin menüsü alan her masaya bir çocuk menüsü.'], ['Yeni menümüz yayında', 'Mevsim lezzetleriyle hazırlanan yeni menümüzü deneyin.']];

// ---------------------------------------------------------------- SQL çıktısı
const statements = []; // { sql, label }
function insert(table, columns, rows, suffix = '') {
  for (let i = 0; i < rows.length; i += BATCH) {
    const chunk = rows.slice(i, i + BATCH).map((r) => '(' + r.join(', ') + ')').join(',\n');
    statements.push({ label: table, sql: `INSERT INTO ${table} (${columns.join(', ')}) VALUES\n${chunk}${suffix ? '\n' + suffix : ''};` });
  }
}

// ---------------------------------------------------------------- 1. kategoriler
const categoryRows = CATEGORIES.map((c, i) => [i + 1, q(c[0]), q(slugify(c[0])), i + 1, 'TRUE', ts(daysAgo(400)), ts(daysAgo(400))]);

// ---------------------------------------------------------------- 2. işletmeler ve şubeler
const businesses = []; const branches = [];
let businessSeq = 0; let branchSeq = 0;
function newBusiness(name, catIndex, createdAt) {
  const id = ++businessSeq;
  const b = { id, name, slug: `${slugify(name)}-${id}`, catIndex, createdAt, branches: [] };
  businesses.push(b);
  return b;
}
function newBranch(business, cityId, name, createdAt) {
  const id = ++branchSeq;
  const district = pick(districtsByCity[cityId]);
  const [lat, lng] = COORDS[cityId];
  const cat = CATEGORIES[business.catIndex];
  const districtLabel = district.name === 'Merkez' ? `${cityById[cityId].name} Merkez` : district.name;
  const br = {
    id, business, cityId, district, name: name ?? districtLabel,
    // Zincirlerin farklı illerinde aynı ilçe adı (ör. "Merkez") olabileceği için il adı da slug'a eklenir.
    slug: `${cityById[cityId].slug}-${district.slug}`,
    lat: (lat + (rand() - 0.5) * 0.12).toFixed(7), lng: (lng + (rand() - 0.5) * 0.12).toFixed(7),
    rate: weighted(RATE_WEIGHTS), createdAt,
    address: `${pick(STREETS)} No:${int(1, 180)}, ${district.name === 'Merkez' ? cityById[cityId].name + ' Merkez' : district.name}/${cityById[cityId].name}`,
    phone: `0850${String(3000000 + id).padStart(7, '0')}`,
    opens: cat[2], closes: cat[3],
  };
  business.branches.push(br); branches.push(br);
  return br;
}

// Zincir markalar: her biri rastgele 8–60 ilde birer şube
const chainCities = CHAINS.map(() => new Set(shuffle(cities.map((c) => c.id)).slice(0, int(8, 60))));
const chainBusinesses = CHAINS.map(([name, catIndex]) => newBusiness(name, catIndex, daysAgo(int(200, 380))));

for (const city of cities) {
  const slots = [];
  CHAINS.forEach((_, i) => { if (chainCities[i].has(city.id)) slots.push(chainBusinesses[i]); });
  for (const chain of slots) {
    newBranch(chain, city.id, null, between(chain.createdAt, daysAgo(5)));
  }
  const usedNames = new Set();
  for (let n = slots.length; n < BRANCHES_PER_CITY; n++) {
    const catIndex = weighted(CATEGORIES.map((_, i) => [i, [0, 1, 2, 7, 11].includes(i) ? 3 : 2]));
    const kind = CATEGORIES[catIndex][1];
    let name;
    do {
      name = chance(0.35) ? `${pick(OWNER_NAMES)} Usta ${kind}` : `${pick(PREFIXES)} ${kind}`;
    } while (usedNames.has(name) && usedNames.size < 500);
    usedNames.add(name);
    const created = chance(0.08) ? daysAgo(int(1, 29)) : daysAgo(int(30, 360));
    const biz = newBusiness(name, catIndex, created);
    newBranch(biz, city.id, null, created);
  }
}

// ---------------------------------------------------------------- 3. panel kullanıcıları
let panelSeq = PANEL_ID_OFFSET;
const panelRows = [];
for (const b of businesses) {
  b.ownerId = ++panelSeq;
  panelRows.push([b.ownerId, q('business_owner'), b.id, 'NULL', q(pick(OWNER_NAMES)), q(pick(LAST)), q(`isletme${b.id}@example.com`), q(`0850${String(1000000 + b.id).padStart(7, '0')}`), q(PASSWORD_HASH), q('active'), ts(b.createdAt), ts(b.createdAt)]);
}
for (const br of branches) {
  br.managerId = ++panelSeq;
  const first = chance(0.5) ? pick(FIRST_M) : pick(FIRST_F);
  panelRows.push([br.managerId, q('branch_manager'), br.business.id, br.id, q(first), q(pick(LAST)), q(`sube${br.id}@example.com`), q(`0850${String(5000000 + br.id).padStart(7, '0')}`), q(PASSWORD_HASH), q('active'), ts(br.createdAt), ts(br.createdAt)]);
}

// ---------------------------------------------------------------- 4. menüler
const menuCatRows = []; const menuItemRows = [];
let menuCatSeq = 0; let menuItemSeq = 0;
for (const b of businesses) {
  const template = CATEGORIES[b.catIndex][4];
  template.forEach(([catName, items], ci) => {
    const mcId = ++menuCatSeq;
    menuCatRows.push([mcId, b.id, q(catName), ci + 1, 'TRUE', ts(b.createdAt), ts(b.createdAt)]);
    const chosen = shuffle(items).slice(0, Math.min(items.length, ci === 0 ? 4 : 3));
    chosen.forEach(([item, desc, min, max], ii) => {
      const price = Math.round(int(min, max) / 5) * 5;
      menuItemRows.push([++menuItemSeq, b.id, mcId, q(item), q(desc), money(price * 100), chance(0.04) ? 'FALSE' : 'TRUE', ii + 1, ts(b.createdAt), ts(b.createdAt)]);
    });
  });
}

// ---------------------------------------------------------------- 5. şube satırları ve saatler
const businessRows = businesses.map((b) => {
  const cat = CATEGORIES[b.catIndex][0];
  return [b.id, q(b.name), q(b.slug), q(`${b.name}, ${cat.toLowerCase()} alanında misafirlerine sıcak bir ortam ve taze lezzetler sunar.`), b.catIndex + 1, q('approved'), q(b.name + ' Gıda Ltd. Şti.'), q(cityById[b.branches[0].cityId].name + ' Vergi Dairesi'), q(String(1000000000 + b.id)), ts(b.createdAt), ts(b.createdAt), ts(b.createdAt)];
});
const branchRows = branches.map((br) => [
  br.id, br.business.id, q(br.name), q(br.slug), br.cityId, br.district.id, q(br.address), br.lat, br.lng, q(br.phone),
  q(`${br.business.name} ${br.name} şubesi.`), money(br.rate),
  q(br.rate > 0 ? 'Biriken ödüller yalnızca bu şubede kullanılabilir. Ödül, ödenen tutar üzerinden hesaplanır. Ödül kullanımı için mobil uygulamadan onay gerekir.' : 'Bu şube şu anda ödül vermemektedir. Daha önce biriken ödüller kullanılabilir.'),
  q('active'), ts(br.createdAt), ts(br.createdAt),
]);
const hourRows = [];
for (const br of branches) {
  const closedDay = chance(0.15) ? 7 : 0;
  for (let d = 1; d <= 7; d++) {
    if (d === closedDay) hourRows.push([br.id, d, 'NULL', 'NULL', 'TRUE']);
    else hourRows.push([br.id, d, q(br.opens), q(br.closes), 'FALSE']);
  }
}

// ---------------------------------------------------------------- 6. kampanyalar, sponsorluk, abonelik
const campaignRows = []; let campaignSeq = 0;
const runningCampaignBranches = new Set();
for (const br of branches) {
  if (!chance(0.25)) continue;
  const id = ++campaignSeq;
  const ended = chance(0.1);
  const starts = ended ? daysAgo(int(60, 90)) : daysAgo(int(1, 15));
  const ends = ended ? daysAgo(int(5, 30)) : new Date(NOW.getTime() + int(20, 90) * 86400000);
  const kind = weighted([['announcement', 40], ['weekday', 25], ['new', 15], ['birthday', 20]]);
  let title; let desc; let rate = 'NULL'; let weekdays = 'NULL'; let newOnly = 'FALSE'; let bday = 'FALSE'; let type = 'rate_override';
  const base = br.rate;
  if (kind === 'announcement') { [title, desc] = pick(ANNOUNCEMENTS); type = 'announcement'; }
  else if (kind === 'weekday') { const r = Math.min(10000, base + 500); rate = money(r); weekdays = "'{1,2,3,4,5}'"; title = `Hafta içi %${r / 100} ödül`; desc = 'Pazartesi–Cuma tüm harcamalarda geçerlidir.'; }
  else if (kind === 'new') { const r = Math.min(10000, base + 1000); rate = money(r); newOnly = 'TRUE'; title = `İlk ziyarete %${r / 100} ödül`; desc = 'Şubemizde ilk kez ödül kazanacak müşterilerimize özel.'; }
  else { const r = Math.min(10000, Math.max(2500, base + 1500)); rate = money(r); bday = 'TRUE'; title = `Doğum gününe özel %${r / 100} ödül`; desc = 'Doğum gününüzde gelin, size özel ödül oranından yararlanın.'; }
  if (!ended) runningCampaignBranches.add(br.id);
  campaignRows.push([id, br.id, q(title), q(desc), q('Kampanya koşulları şube tarafından belirlenir.'), q(type), rate, weekdays, newOnly, bday, ts(starts), ts(ends), q(ended ? 'ended' : 'active'), br.managerId, ts(starts), ts(starts)]);
}

const sponsorshipRows = []; let sponsorSeq = 0;
for (const city of cities) {
  for (const br of shuffle(branches.filter((b) => b.cityId === city.id)).slice(0, 3)) {
    sponsorshipRows.push([++sponsorSeq, br.id, "'2026-11-01'", q('active'), ts(daysAgo(12)), ts(daysAgo(10))]);
  }
}

const subscriptionRows = []; let subSeq = 0;
const ADMIN = "(SELECT id FROM panel_users WHERE role = 'platform_admin' ORDER BY id LIMIT 1)";
for (const br of branches) {
  if (!chance(0.04)) continue;
  const yearly = chance(0.3);
  const starts = daysAgo(int(1, 40));
  const ends = new Date(starts.getTime() + (yearly ? 365 : 31) * 86400000);
  subscriptionRows.push([++subSeq, br.id, q(yearly ? 'yearly' : 'monthly'), ts(starts), ts(ends), yearly ? '4990.00' : '499.00', q('active'), ADMIN, ts(starts), ts(starts)]);
}

// ---------------------------------------------------------------- 7. müşteriler
const CITY_WEIGHTS = { 34: 18, 6: 7, 35: 5, 16: 4, 7: 3, 42: 3, 1: 3, 27: 3, 63: 2, 33: 2, 41: 2, 21: 2, 45: 2, 31: 2, 38: 2, 55: 2, 10: 1.5, 20: 1.5, 26: 1.5, 61: 1.5 };
const cityWeightList = cities.map((c) => [c.id, CITY_WEIGHTS[c.id] ?? 0.8]);
const customers = [];
const customerRows = [];
for (let i = 1; i <= CUSTOMER_COUNT; i++) {
  const female = chance(0.5);
  const first = female ? pick(FIRST_F) : pick(FIRST_M);
  const last = pick(LAST);
  const cityId = weighted(cityWeightList);
  const district = pick(districtsByCity[cityId]);
  const created = daysAgo(int(10, 200));
  const birth = new Date(Date.UTC(int(1960, 2006), int(0, 11), int(1, 28)));
  const c = { id: i, cityId, district, created, first, last };
  customers.push(c);
  customerRows.push([i, i, q(first), q(last), q(`0500${String(i).padStart(7, '0')}`), q(`musteri${i}@example.com`), q(PASSWORD_HASH), cityId, district.id,
    `'${birth.getUTCFullYear()}-${pad(birth.getUTCMonth() + 1)}-${pad(birth.getUTCDate())}'`, q(chance(0.2) ? 'full' : 'initials'), ts(created), q('active'), ts(created), ts(created)]);
}

// ---------------------------------------------------------------- 8. işlemler, kumbaralar, yorumlar
// Her ilde şubelere popülerlik ağırlığı (bazı şubeler çok daha fazla müşteri çeker)
const cityBranchWeights = {};
for (const city of cities) {
  const list = shuffle(branches.filter((b) => b.cityId === city.id && b.rate > 0));
  cityBranchWeights[city.id] = list.map((b, rank) => [b, 1 / Math.pow(rank + 1, 0.85)]);
}
const allEarnable = branches.filter((b) => b.rate > 0).map((b) => [b, 1]);

const walletRows = []; const txRows = []; const approvalRows = []; const monthlyCustomerRows = [];
const counters = new Map(); // `${branchId}|${period}` -> count
const monthlySeen = new Set();
const reviewRows = []; const replyRows = []; const favoriteRows = [];
let walletSeq = 0; let txSeq = 0; let approvalSeq = 0; let reviewSeq = 0; let replySeq = 0; let mbcSeq = 0;
const walletsWithBalance = [];

for (const c of customers) {
  const visitCount = weighted([[1, 25], [2, 25], [3, 20], [4, 12], [5, 8], [6, 5], [8, 3], [10, 2]]);
  const visited = new Set();
  for (let v = 0; v < visitCount; v++) {
    const br = chance(0.85) ? weighted(cityBranchWeights[c.cityId]) : weighted(allEarnable);
    if (visited.has(br.id)) continue;
    visited.add(br.id);

    const walletId = ++walletSeq;
    const from = new Date(Math.max(c.created.getTime(), br.createdAt.getTime()));
    if (from >= NOW) { walletSeq--; visited.delete(br.id); continue; }

    const earnCount = weighted([[1, 30], [2, 25], [3, 18], [4, 12], [5, 8], [6, 7]]);
    const events = [];
    for (let e = 0; e < earnCount; e++) events.push({ type: 'earn', at: between(from, NOW) });
    events.sort((a, b) => a.at - b.at);
    if (chance(0.3) && events.length > 0) {
      const after = events[int(0, events.length - 1)].at;
      events.push({ type: 'redeem', at: between(addMinutes(after, 60), NOW), frac: 0.2 + rand() * 0.8 });
      events.sort((a, b) => a.at - b.at);
    }

    let balance = 0; let earned = 0; let redeemed = 0; let firstEarnAt = null;
    const lastAt = events[events.length - 1].at;
    for (const ev of events) {
      if (ev.type === 'earn') {
        const spend = int(120, 2800) * 100 + (chance(0.3) ? 50 : 0);
        const reward = pctOf(spend, br.rate);
        if (reward <= 0) continue;
        balance += reward; earned += reward;
        const txId = ++txSeq;
        firstEarnAt ??= ev.at;
        txRows.push([txId, walletId, br.id, c.id, q('earn'), money(reward), money(spend), money(br.rate), 'NULL', money(balance), 'NULL', 'NULL', br.managerId, ts(ev.at)]);
        const period = periodOf(ev.at);
        const key = `${br.id}|${period}|${c.id}`;
        if (!monthlySeen.has(key)) {
          monthlySeen.add(key);
          monthlyCustomerRows.push([++mbcSeq, br.id, `'${period}'`, c.id, txId, ts(ev.at)]);
          const ck = `${br.id}|${period}`;
          counters.set(ck, (counters.get(ck) ?? 0) + 1);
        }
      } else {
        const amount = Math.floor(balance * ev.frac);
        if (amount < 100) continue;
        balance -= amount; redeemed += amount;
        const arId = ++approvalSeq;
        approvalRows.push([arId, q('redeem'), walletId, br.id, c.id, money(amount), 'NULL', 'NULL', q('approved'), br.managerId, ts(addMinutes(ev.at, 1)), ts(addMinutes(ev.at, -1)), ts(addMinutes(ev.at, 1))]);
        txRows.push([++txSeq, walletId, br.id, c.id, q('redeem'), money(-amount), 'NULL', 'NULL', 'NULL', money(balance), 'NULL', arId, br.managerId, ts(ev.at)]);
      }
    }
    if (earned === 0) { walletSeq--; continue; }
    walletRows.push([walletId, c.id, br.id, money(balance), money(earned), money(redeemed), ts(firstEarnAt), ts(lastAt)]);
    if (balance >= 5000) walletsWithBalance.push({ walletId, c, br, balance });

    // Yorum (ödül kazanılmış şubeye)
    if (chance(0.45)) {
      const score = weighted([[10, 20], [9, 22], [8, 22], [7, 14], [6, 8], [5, 5], [4, 3], [3, 2], [2, 2], [1, 2]]);
      const bucket = score >= 8 ? 'high' : score >= 5 ? 'mid' : 'low';
      const at = between(addMinutes(firstEarnAt, 120), NOW);
      const reviewId = ++reviewSeq;
      reviewRows.push([reviewId, br.id, c.id, score, chance(0.15) ? 'NULL' : q(pick(COMMENTS[bucket])), q('published'), ts(at), ts(at)]);
      if (chance(0.2)) {
        const rat = between(at, NOW);
        replyRows.push([++replySeq, reviewId, br.managerId, q(score >= 8 ? REPLIES[0] : score >= 5 ? REPLIES[2] : REPLIES[1]), ts(rat), ts(rat)]);
      }
    }
    if (chance(0.3)) favoriteRows.push([c.id, br.id, ts(between(firstEarnAt, NOW))]);
  }
}

// Mobil uygulamada Bildirimler ekranını denemek için birkaç bekleyen ödül kullanım isteği
for (const w of shuffle(walletsWithBalance).slice(0, 25)) {
  const amount = Math.max(100, Math.floor(w.balance * 0.3));
  const at = new Date(NOW.getTime() - int(10, 600) * 60000);
  approvalRows.push([++approvalSeq, q('redeem'), w.walletId, w.br.id, w.c.id, money(amount), 'NULL', 'NULL', q('pending'), w.br.managerId, 'NULL', ts(at), ts(at)]);
}

const counterRows = [...counters.entries()].map(([k, n], i) => {
  const [branchId, period] = k.split('|');
  return [i + 1, branchId, `'${period}'`, n, ts(NOW), ts(NOW)];
});

// ---------------------------------------------------------------- SQL ifadeleri
statements.push({ label: 'kontrol', sql: `DO $$
BEGIN
    IF (SELECT count(*) FROM districts) <> 973
       OR (SELECT sum(id::bigint * city_id) FROM districts) <> ${districtChecksum} THEN
        RAISE EXCEPTION 'Il/ilce verileri beklenen gibi degil. Once kumbare-il-ilce.sql yuklenmeli.';
    END IF;
    IF EXISTS (SELECT 1 FROM businesses) OR EXISTS (SELECT 1 FROM customers) THEN
        RAISE EXCEPTION 'Ornek veriler yalnizca bos veritabanina eklenebilir (isletme veya musteri tablosu dolu).';
    END IF;
    -- Kategoriler ya hiç yok ya da daha önceki örnek yüklemesinden kalmış olmalı.
    IF EXISTS (SELECT 1 FROM categories)
       AND (SELECT count(*) FROM categories WHERE (id, slug) IN (${CATEGORIES.map((c, i) => `(${i + 1}, '${slugify(c[0])}')`).join(', ')})) <> ${CATEGORIES.length} THEN
        RAISE EXCEPTION 'categories tablosunda ornek veriyle uyusmayan kayitlar var.';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM panel_users WHERE role = 'platform_admin') THEN
        RAISE EXCEPTION 'Once platform yoneticisi olusturulmali (kumbare:create-admin).';
    END IF;
    IF EXISTS (SELECT 1 FROM panel_users WHERE id > ${PANEL_ID_OFFSET}) THEN
        RAISE EXCEPTION 'panel_users tablosunda ${PANEL_ID_OFFSET} uzeri id zaten var.';
    END IF;
END $$;` });

insert('categories', ['id', 'name', 'slug', 'sort_order', 'is_active', 'created_at', 'updated_at'], categoryRows, 'ON CONFLICT (id) DO NOTHING');
insert('businesses', ['id', 'name', 'slug', 'description', 'category_id', 'status', 'legal_name', 'tax_office', 'tax_number', 'approved_at', 'created_at', 'updated_at'], businessRows);
insert('branches', ['id', 'business_id', 'name', 'slug', 'city_id', 'district_id', 'address', 'latitude', 'longitude', 'phone', 'description', 'reward_rate', 'reward_terms', 'status', 'created_at', 'updated_at'], branchRows);
insert('branch_hours', ['branch_id', 'weekday', 'opens_at', 'closes_at', 'is_closed'], hourRows);
insert('panel_users', ['id', 'role', 'business_id', 'branch_id', 'first_name', 'last_name', 'email', 'phone', 'password', 'status', 'created_at', 'updated_at'], panelRows);
insert('menu_categories', ['id', 'business_id', 'name', 'sort_order', 'is_active', 'created_at', 'updated_at'], menuCatRows);
insert('menu_items', ['id', 'business_id', 'menu_category_id', 'name', 'description', 'price', 'is_available', 'sort_order', 'created_at', 'updated_at'], menuItemRows);
insert('campaigns', ['id', 'branch_id', 'title', 'description', 'terms', 'type', 'reward_rate', 'weekdays', 'new_customers_only', 'birthday_only', 'starts_at', 'ends_at', 'status', 'created_by', 'created_at', 'updated_at'], campaignRows);
insert('sponsorships', ['id', 'branch_id', 'month', 'status', 'created_at', 'updated_at'], sponsorshipRows);
insert('subscriptions', ['id', 'branch_id', 'billing_cycle', 'starts_at', 'ends_at', 'price', 'status', 'activated_by', 'created_at', 'updated_at'], subscriptionRows);
insert('customers', ['id', 'customer_no', 'first_name', 'last_name', 'phone', 'email', 'password', 'city_id', 'district_id', 'birth_date', 'display_name_mode', 'kvkk_consent_at', 'status', 'created_at', 'updated_at'], customerRows);
insert('wallets', ['id', 'customer_id', 'branch_id', 'balance', 'total_earned', 'total_redeemed', 'created_at', 'updated_at'], walletRows);
insert('approval_requests', ['id', 'type', 'wallet_id', 'branch_id', 'customer_id', 'amount', 'related_transaction_id', 'reason', 'status', 'requested_by', 'responded_at', 'created_at', 'updated_at'], approvalRows);
insert('wallet_transactions', ['id', 'wallet_id', 'branch_id', 'customer_id', 'type', 'amount', 'spend_amount', 'reward_rate', 'campaign_id', 'balance_after', 'related_transaction_id', 'approval_request_id', 'performed_by', 'created_at'], txRows);
insert('monthly_branch_customers', ['id', 'branch_id', 'period', 'customer_id', 'first_transaction_id', 'created_at'], monthlyCustomerRows);
insert('monthly_branch_counters', ['id', 'branch_id', 'period', 'unique_customers', 'created_at', 'updated_at'], counterRows);
insert('reviews', ['id', 'branch_id', 'customer_id', 'score', 'comment', 'status', 'created_at', 'updated_at'], reviewRows);
insert('review_replies', ['id', 'review_id', 'panel_user_id', 'body', 'created_at', 'updated_at'], replyRows);
insert('favorites', ['customer_id', 'branch_id', 'created_at'], favoriteRows);

statements.push({ label: 'son', sql: `-- Puan önbelleği
UPDATE branches b SET rating_count = r.cnt, rating_avg = r.avg
FROM (SELECT branch_id, COUNT(*) AS cnt, ROUND(AVG(score)::numeric, 1) AS avg FROM reviews WHERE status = 'published' GROUP BY branch_id) r
WHERE r.branch_id = b.id;

-- Popülerlik (son 30 gün)
UPDATE branches b SET popularity_score = t.cnt
FROM (SELECT branch_id, COUNT(*) AS cnt FROM wallet_transactions
      WHERE created_at >= TIMESTAMPTZ '2026-11-11 12:00:00+03' - interval '30 days' AND type IN ('earn', 'redeem')
      GROUP BY branch_id) t
WHERE t.branch_id = b.id;

-- Müşteri numarası sayacı
UPDATE customer_no_counter SET last_value = GREATEST(last_value, ${CUSTOMER_COUNT}) WHERE id = 1;

-- Sequence'leri en büyük id'nin ötesine al
${['categories', 'businesses', 'branches', 'branch_hours', 'panel_users', 'menu_categories', 'menu_items', 'campaigns', 'sponsorships', 'subscriptions', 'customers', 'wallets', 'approval_requests', 'wallet_transactions', 'monthly_branch_customers', 'monthly_branch_counters', 'reviews', 'review_replies', 'favorites']
    .map((t) => `SELECT setval(pg_get_serial_sequence('${t}', 'id'), GREATEST((SELECT COALESCE(MAX(id), 0) FROM ${t}), 1));`).join('\n')}` });

// ---------------------------------------------------------------- dosyalara bölme
for (const f of readdirSync(HERE)) if (/^\d\d-ornek-veri\.sql$/.test(f)) unlinkSync(join(HERE, f));

const files = []; let current = []; let size = 0;
for (const st of statements) {
  const bytes = Buffer.byteLength(st.sql, 'utf8');
  if (current.length && size + bytes > MAX_FILE_BYTES) { files.push(current); current = []; size = 0; }
  current.push(st); size += bytes;
}
if (current.length) files.push(current);

files.forEach((group, i) => {
  const labels = [...new Set(group.map((s) => s.label))].join(', ');
  const header = `-- Kumbare örnek veri — dosya ${i + 1}/${files.length}\n-- İçerik: ${labels}\n-- Dosyaları numara sırasıyla yükleyin. Hepsinin şifresi: Kumbare2026!\n\nBEGIN;\nSET client_encoding = 'UTF8';\n\n`;
  writeFileSync(join(HERE, `${String(i + 1).padStart(2, '0')}-ornek-veri.sql`), header + group.map((s) => s.sql).join('\n\n') + '\n\nCOMMIT;\n', 'utf8');
});

const stats = {
  dosya: files.length, kategori: categoryRows.length, isletme: businesses.length, zincir: CHAINS.length, sube: branches.length,
  panel_kullanici: panelRows.length, menu_urun: menuItemRows.length, kampanya: campaignRows.length, sponsorluk: sponsorshipRows.length,
  pro_abonelik: subscriptionRows.length, musteri: customers.length, kumbara: walletRows.length, islem: txRows.length,
  bekleyen_onay: approvalRows.filter((r) => r[8] === "'pending'").length, yorum: reviewRows.length, yorum_cevabi: replyRows.length, favori: favoriteRows.length,
};
console.log(JSON.stringify(stats, null, 1));
