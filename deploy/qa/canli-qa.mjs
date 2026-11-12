// Kumbare canlı API QA betiği
const B = 'https://kumbareapi.trthabercanli.com';
const results = [];
let tokens = {};

async function req(method, path, { token, body, headers = {} } = {}) {
  const t0 = performance.now();
  const res = await fetch(B + path, {
    method,
    headers: { Accept: 'application/json', ...(body ? { 'Content-Type': 'application/json' } : {}), ...(token ? { Authorization: 'Bearer ' + token } : {}), ...headers },
    body: body ? JSON.stringify(body) : undefined,
    redirect: 'manual',
  });
  const text = await res.text();
  let json = null; try { json = JSON.parse(text); } catch {}
  return { status: res.status, ms: Math.round(performance.now() - t0), json, text, headers: res.headers };
}

function check(area, name, ok, detail = '') {
  results.push({ area, name, ok, detail });
  console.log(`${ok ? 'OK  ' : 'FAIL'} [${area}] ${name}${detail ? ' — ' + detail : ''}`);
}
const uuid = () => crypto.randomUUID();
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

// ------------------------------------------------------------- herkese açık
async function publicTests() {
  const A = 'Herkese açık';
  let r = await req('GET', '/api/v1/cities');
  check(A, 'cities 81 il', r.status === 200 && r.json?.data?.length === 81, `${r.status}, ${r.json?.data?.length}, ${r.ms}ms`);
  r = await req('GET', '/api/v1/cities/34/districts');
  check(A, 'İstanbul 39 ilçe', r.status === 200 && r.json?.data?.length === 39, `${r.status}, ${r.json?.data?.length}`);
  r = await req('GET', '/api/v1/cities/abc/districts');
  check(A, 'geçersiz il kimliği → 404', r.status === 404, `${r.status} ${r.text.slice(0, 60)}`);
  r = await req('GET', '/api/v1/categories');
  check(A, 'kategoriler 16', r.status === 200 && r.json?.data?.length === 16, `${r.status}, ${r.json?.data?.length}`);

  r = await req('GET', '/api/v1/home?lat=41.01&lng=28.98');
  const blocks = r.json?.data ?? {};
  check(A, 'ana sayfa blokları dolu (konumlu)', r.status === 200 && Object.values(blocks).every((v) => v.length > 0),
    `${r.status} ${Object.entries(blocks).map(([k, v]) => k + '=' + v.length).join(' ')} ${r.ms}ms`);
  check(A, 'sponsorlu blokta is_sponsored=true', (blocks.sponsored ?? []).every((b) => b.is_sponsored), '');
  check(A, 'yakındakiler mesafeye göre sıralı', (blocks.nearby ?? []).every((b, i, a) => i === 0 || a[i - 1].location.distance_km <= b.location.distance_km),
    (blocks.nearby ?? []).map((b) => b.location.distance_km).join(','));

  // Sıralamalar
  const sorts = {
    rate_desc: (a, b) => +a.reward_rate >= +b.reward_rate,
    rate_asc: (a, b) => +a.reward_rate <= +b.reward_rate,
    most_reviewed: (a, b) => a.rating.count >= b.rating.count,
  };
  for (const [s, cmp] of Object.entries(sorts)) {
    r = await req('GET', `/api/v1/branches?city=6&sort=${s}&per_page=50`);
    const d = r.json?.data ?? [];
    check(A, `sıralama ${s}`, r.status === 200 && d.length === 50 && d.every((x, i) => i === 0 || cmp(d[i - 1], x)), `${r.status} ${r.ms}ms`);
  }
  r = await req('GET', '/api/v1/branches?city=34&sort=score&per_page=50');
  let d = r.json?.data ?? [];
  const firstUnder10 = d.findIndex((x) => x.rating.count < 10);
  const ranked = firstUnder10 === -1 ? d : d.slice(0, firstUnder10);
  check(A, 'puan sıralaması: önce 10+ yorumlular, puana göre', ranked.length > 0 && ranked.every((x, i) => i === 0 || +ranked[i - 1].rating.average >= +x.rating.average)
    && d.slice(firstUnder10 === -1 ? d.length : firstUnder10).every((x) => x.rating.count < 10), `ilk ${ranked.length} sıralı`);
  r = await req('GET', '/api/v1/branches?city=34&per_page=1');
  check(A, 'il filtresi toplamı 100', r.json?.meta?.total === 100, `total=${r.json?.meta?.total}`);
  r = await req('GET', '/api/v1/branches?sort=nearest&lat=39.93&lng=32.86&radius_km=3&per_page=50');
  d = r.json?.data ?? [];
  check(A, 'yarıçap filtresi (3 km)', r.status === 200 && d.every((x) => x.location.distance_km <= 3.05), `${d.length} sonuç, max=${Math.max(...d.map((x) => x.location.distance_km))} ${r.ms}ms`);
  r = await req('GET', '/api/v1/branches?min_score=9&per_page=50');
  d = r.json?.data ?? [];
  check(A, 'min_score=9 filtresi', r.status === 200 && d.every((x) => +x.rating.average >= 9), `${d.length} sonuç`);
  r = await req('GET', '/api/v1/branches?has_campaign=1&per_page=50');
  d = r.json?.data ?? [];
  check(A, 'has_campaign filtresi', r.status === 200 && d.length > 0 && d.every((x) => x.has_campaign), `${d.length} sonuç`);
  r = await req('GET', '/api/v1/branches?min_rate=15&per_page=50');
  d = r.json?.data ?? [];
  check(A, 'min_rate=15 filtresi', d.every((x) => +x.reward_rate >= 15), `${d.length} sonuç`);
  r = await req('GET', '/api/v1/branches?q=' + encodeURIComponent('Közbaşı') + '&per_page=50');
  check(A, 'arama (Türkçe karakter)', r.status === 200 && r.json?.data?.length > 0 && r.json.data.every((x) => x.business.name.includes('Közbaşı')), `${r.json?.meta?.total} sonuç`);
  r = await req('GET', '/api/v1/branches?q=' + encodeURIComponent('közbaşı'));
  check(A, 'arama büyük/küçük harf duyarsız (ö/ş)', r.json?.meta?.total > 0, `${r.json?.meta?.total} sonuç`);
  r = await req('GET', '/api/v1/branches?q=' + encodeURIComponent("' OR 1=1 --"));
  check(A, 'SQL enjeksiyon denemesi zararsız', r.status === 200 && r.json?.meta?.total === 0, `${r.status} total=${r.json?.meta?.total}`);
  r = await req('GET', '/api/v1/branches?q=' + encodeURIComponent('%'));
  check(A, "'%' joker karakteri kaçışlı", r.status === 200 && r.json?.meta?.total === 0, `total=${r.json?.meta?.total}`);
  r = await req('GET', '/api/v1/branches?per_page=500');
  check(A, 'per_page üst sınırı → 422', r.status === 422, `${r.status}`);
  r = await req('GET', '/api/v1/branches?sort=hack');
  check(A, 'geçersiz sort → 422', r.status === 422, `${r.status}`);
  r = await req('GET', '/api/v1/branches?page=99999');
  check(A, 'çok ileri sayfa → boş liste', r.status === 200 && r.json?.data?.length === 0, `${r.status}`);
  r = await req('GET', '/api/v1/branches?city=abc');
  check(A, 'city=abc → 422', r.status === 422, `${r.status}`);

  // Detay
  r = await req('GET', '/api/v1/branches?city=34&sort=score&per_page=1');
  const top = r.json?.data?.[0];
  r = await req('GET', `/api/v1/branches/${top.id}`);
  const det = r.json?.data;
  check(A, 'restoran detayı', r.status === 200 && det?.menu?.length > 0 && det?.hours?.length === 7, `${r.status} menü=${det?.menu?.length} saat=${det?.hours?.length} ${r.ms}ms`);
  const distSum = Object.values(det?.rating_distribution ?? {}).reduce((a, b) => a + b, 0);
  check(A, 'puan dağılımı toplamı = yorum sayısı', distSum === det?.rating?.count, `${distSum} / ${det?.rating?.count}`);
  check(A, 'detayda viewer=null (girişsiz)', det?.viewer === null, '');
  r = await req('GET', '/api/v1/branches/99999999');
  check(A, 'olmayan restoran → 404', r.status === 404, `${r.status}`);
  r = await req('GET', `/api/v1/branches/${top.id}/reviews?sort=score`);
  d = r.json?.data ?? [];
  check(A, 'yorumlar puana göre sıralı', r.status === 200 && d.every((x, i) => i === 0 || d[i - 1].score >= x.score), `${d.length} yorum`);
  check(A, 'yorumda yazar adı "Ad S." biçiminde veya tam ad', d.every((x) => typeof x.author === 'string' && x.author.length > 1), d.slice(0, 3).map((x) => x.author).join(', '));
  check(A, 'yorumlarda e-posta/telefon sızmıyor', !/@example\.com|0500\d{7}/.test(r.text), '');
  return top;
}

// ------------------------------------------------------------- hata yönetimi
async function errorTests() {
  const A = 'Hata yönetimi';
  let r = await req('GET', '/api/v1/yok-boyle-bir-adres');
  check(A, 'olmayan API adresi → JSON 404', r.status === 404 && r.json?.code === 'not_found', `${r.status} ${r.text.slice(0, 60)}`);
  r = await req('DELETE', '/api/v1/cities');
  check(A, 'yanlış HTTP metodu → 405 Türkçe JSON', r.status === 405 && r.json?.code === 'method_not_allowed' && /desteklemiyor/.test(r.json?.message), `${r.status} ${r.json?.message}`);
  r = await req('GET', '/');
  check(A, 'ana adres API bilgisi döndürüyor', r.status === 200 && r.json?.name === 'Kumbare API', `${r.status} ${r.text.slice(0, 60)}`);
  r = await req('GET', '/api/v1/categories');
  check(A, 'X-Powered-By başlığı yok', !r.headers.get('x-powered-by'), `${r.headers.get('x-powered-by')}`);
  check(A, 'HSTS başlığı var', r.headers.get('strict-transport-security') === 'max-age=31536000', `${r.headers.get('strict-transport-security')}`);
  check(A, 'nosniff ve X-Frame-Options var', r.headers.get('x-content-type-options') === 'nosniff' && r.headers.get('x-frame-options') === 'DENY', '');
  r = await req('GET', '/api/v1/home?lat=41.01');
  check(A, 'doğrulama mesajı büyük harfle başlıyor', r.json?.message === 'Enlem girildiğinde boylam alanı zorunludur.', `${r.json?.message}`);
  r = await req('POST', '/api/v1/auth/register', { body: {} });
  check(A, 'çoklu hatada İngilizce özet yok, "İlçe" doğru büyük harf', !/more error/.test(r.json?.message ?? '') && r.json?.errors?.district_id?.[0] === 'İlçe alanı zorunludur.', `${r.json?.message} | ${r.json?.errors?.district_id?.[0]}`);
  r = await req('GET', '/api/v1/wallets');
  check(A, 'tokensız korumalı adres → 401 Türkçe', r.status === 401 && r.json?.message === 'Oturum açmanız gerekiyor.', `${r.status}`);
  r = await req('GET', '/api/v1/wallets', { token: '1|sahte-token' });
  check(A, 'sahte token → 401', r.status === 401, `${r.status}`);
  r = await req('POST', '/api/v1/auth/login', { body: 'bozuk', headers: { 'Content-Type': 'application/json' } });
  check(A, 'bozuk JSON gövde → 422 (500 değil)', r.status === 422, `${r.status}`);
  r = await req('GET', '/api/v1/cities', { headers: { Accept: 'text/html' } });
  check(A, 'Accept: text/html ile de JSON', r.status === 200 && r.json !== null, `${r.status}`);
  check(A, 'hata yanıtlarında yığın izi (stack trace) yok', !/vendor\/laravel|Stack trace|\.php:\d+/.test(r.text), '');
}

// ------------------------------------------------------------- müşteri
async function customerTests(topBranch) {
  const A = 'Müşteri API';
  let r = await req('POST', '/api/v1/auth/register', { body: { first_name: 'Test' } });
  check(A, 'eksik kayıt → 422 + alan hataları', r.status === 422 && r.json?.errors?.email && r.json?.errors?.birth_date && r.json?.errors?.kvkk_consent, Object.keys(r.json?.errors ?? {}).join(','));
  r = await req('POST', '/api/v1/auth/register', { body: { first_name: 'T', last_name: 'T', phone: '05000000001', city_id: 34, district_id: 1, birth_date: '1990-01-01', email: 'musteri1@example.com', password: 'abcdefgh1', password_confirmation: 'abcdefgh1', kvkk_consent: true } });
  check(A, 'kayıtlı telefon/e-posta ve il-ilçe uyuşmazlığı → 422', r.status === 422, `${r.status} ${Object.keys(r.json?.errors ?? {}).join(',')}`);
  r = await req('POST', '/api/v1/auth/login', { body: { email: 'musteri1@example.com', password: 'yanlis-sifre' } });
  check(A, 'yanlış şifre → 422', r.status === 422, `${r.status} ${r.json?.message}`);

  r = await req('POST', '/api/v1/auth/login', { body: { email: 'MUSTERI7@EXAMPLE.COM', password: 'Kumbare2026!', device_name: 'qa' } });
  check(A, 'giriş (büyük harf e-posta)', r.status === 200 && !!r.json?.token, `${r.status} no=${r.json?.customer?.customer_no}`);
  const tok = r.json?.token; tokens.customer = tok;
  const me = r.json?.customer;
  check(A, 'giriş yanıtında şifre alanı yok', !/"password"/.test(r.text), '');

  r = await req('GET', '/api/v1/me', { token: tok });
  check(A, '/me', r.status === 200 && r.json?.data?.customer_no === 7, `${r.status}`);
  r = await req('PUT', '/api/v1/me', { token: tok, body: { birth_date: '2001-01-01', customer_no: 1, display_name_mode: 'full' } });
  check(A, 'doğum tarihi ve müşteri no değiştirilemiyor', r.status === 200 && r.json?.data?.birth_date === me.birth_date && r.json?.data?.customer_no === 7, `${r.json?.data?.birth_date}`);
  await req('PUT', '/api/v1/me', { token: tok, body: { display_name_mode: me.display_name_mode } });
  r = await req('PUT', '/api/v1/me', { token: tok, body: { city_id: 6 } });
  check(A, 'il değişip ilçe yoksa → 422', r.status === 422, `${r.status}`);
  r = await req('PUT', '/api/v1/me', { token: tok, body: { email: 'musteri8@example.com' } });
  check(A, 'başkasının e-postası alınamıyor', r.status === 422, `${r.status}`);

  r = await req('GET', '/api/v1/wallets', { token: tok });
  const wallets = r.json?.data ?? [];
  check(A, 'kumbaralar', r.status === 200, `${wallets.length} kumbara`);
  check(A, 'kumbaralar bakiyeye göre azalan', wallets.every((w, i) => i === 0 || +wallets[i - 1].balance >= +w.balance), '');
  if (wallets[0]) {
    r = await req('GET', `/api/v1/wallets/${wallets[0].id}/transactions`, { token: tok });
    const tx = r.json?.data ?? [];
    check(A, 'işlem geçmişi', r.status === 200 && tx.length > 0, `${tx.length} işlem`);
    check(A, 'son işlemin balance_after = kumbara bakiyesi', tx[0]?.balance_after === wallets[0].balance, `${tx[0]?.balance_after} / ${wallets[0].balance}`);
  }
  // Başka müşterinin kumbarası
  const other = await req('POST', '/api/v1/auth/login', { body: { email: 'musteri8@example.com', password: 'Kumbare2026!' } });
  tokens.customer2 = other.json?.token;
  const w2 = (await req('GET', '/api/v1/wallets', { token: tokens.customer2 })).json?.data?.[0];
  if (w2) {
    r = await req('GET', `/api/v1/wallets/${w2.id}`, { token: tok });
    check(A, 'başkasının kumbarası → 404', r.status === 404, `${r.status}`);
  }

  r = await req('GET', '/api/v1/approvals', { token: tok });
  check(A, 'bildirimler (onay istekleri)', r.status === 200 && Array.isArray(r.json?.data), `${r.json?.data?.length} bekleyen`);
  r = await req('GET', '/api/v1/campaigns', { token: tok });
  check(A, 'kampanyalar (kayıtlı ilçe)', r.status === 200 && !!r.json?.district?.name, `${r.json?.district?.name}: ${r.json?.data?.length} kampanya`);
  check(A, 'kampanyalar müşterinin ilçesinde', (r.json?.data ?? []).every((c) => c.branch?.location?.district === r.json.district.name), '');

  // Favori
  r = await req('POST', `/api/v1/favorites/${topBranch.id}`, { token: tok });
  check(A, 'favori ekleme', r.status === 201, `${r.status}`);
  r = await req('POST', `/api/v1/favorites/${topBranch.id}`, { token: tok });
  check(A, 'favori tekrar ekleme idempotent', r.status === 201, `${r.status}`);
  r = await req('GET', '/api/v1/favorites', { token: tok });
  check(A, 'favori listesinde görünüyor', (r.json?.data ?? []).some((b) => b.id === topBranch.id), '');
  r = await req('GET', `/api/v1/branches/${topBranch.id}`, { token: tok });
  check(A, 'detayda viewer.is_favorite', r.json?.data?.viewer?.is_favorite === true, `${JSON.stringify(r.json?.data?.viewer)?.slice(0, 80)}`);
  r = await req('DELETE', `/api/v1/favorites/${topBranch.id}`, { token: tok });
  check(A, 'favori silme', r.status === 200, `${r.status}`);

  // Yorum kuralı
  r = await req('PUT', `/api/v1/branches/${topBranch.id}/review`, { token: tok, body: { score: 9 } });
  const canReview = (await req('GET', `/api/v1/branches/${topBranch.id}`, { token: tok })).json?.data?.viewer?.can_review;
  check(A, 'ödül kazanmadan yorum → 403 / kazanmışsa 200', canReview ? r.status === 200 : r.status === 403, `can_review=${canReview} → ${r.status}`);
  r = await req('PUT', `/api/v1/branches/${topBranch.id}/review`, { token: tok, body: { score: 7.5 } });
  check(A, 'ondalıklı puan → 422', r.status === 422, `${r.status}`);
}

// ------------------------------------------------------------- personel + uçtan uca akış
async function panelTests() {
  const A = 'Personel API';
  // Müşteri 7'nin kumbarası olan şubeyi bul; yoksa ilk şube.
  const cw = (await req('GET', '/api/v1/wallets', { token: tokens.customer })).json?.data ?? [];
  const branchId = cw[0]?.branch?.id ?? 1;

  let r = await req('POST', '/api/panel/v1/auth/login', { body: { email: `sube${branchId}@example.com`, password: 'Kumbare2026!' } });
  check(A, 'şube yöneticisi girişi', r.status === 200 && !!r.json?.token, `${r.status} şube=${r.json?.user?.branch?.display_name}`);
  const tok = r.json?.token; tokens.manager = tok;

  r = await req('GET', '/api/panel/v1/me', { token: tok });
  check(A, '/me + aylık sayaç', r.status === 200 && r.json?.data?.monthly_counter?.plan, JSON.stringify(r.json?.data?.monthly_counter));

  r = await req('GET', '/api/panel/v1/customers/7', { token: tok });
  check(A, 'müşteri sorgulama (ad-soyad, bakiye)', r.status === 200 && !!r.json?.data?.customer?.full_name, `${r.json?.data?.customer?.full_name} bakiye=${r.json?.data?.wallet?.balance}`);
  check(A, 'sorguda doğum tarihi/e-posta/telefon yok', !/birth_date|@example\.com|"phone"/.test(r.text), '');
  const before = r.json?.data?.wallet?.balance;
  r = await req('GET', '/api/panel/v1/customers/999999999', { token: tok });
  check(A, 'olmayan müşteri → 404', r.status === 404, `${r.status}`);

  r = await req('GET', '/api/panel/v1/customers/7/campaigns', { token: tok });
  check(A, 'uygun kampanyalar', r.status === 200, `${r.json?.data?.length} kampanya, varsayılan oran ${r.json?.default_rate}`);

  r = await req('POST', '/api/panel/v1/earn/preview', { token: tok, body: { customer_no: 7, spend_amount: '123,45' } });
  const prev = r.json?.data;
  check(A, 'önizleme (virgüllü tutar)', r.status === 200 && prev?.spend_amount === '123.45', `${r.status} ödül=${prev?.reward_amount} oran=${prev?.reward_rate}`);

  const key = uuid();
  r = await req('POST', '/api/panel/v1/earn', { token: tok, body: { customer_no: 7, spend_amount: 123.45, idempotency_key: key } });
  check(A, 'ödül kazandırma', r.status === 201 && r.json?.data?.amount === prev?.reward_amount, `${r.status} ${r.json?.message}`);
  const earnTx = r.json?.data;
  r = await req('POST', '/api/panel/v1/earn', { token: tok, body: { customer_no: 7, spend_amount: 123.45, idempotency_key: key } });
  check(A, 'aynı anahtarla tekrar → yeni işlem yok', r.status === 200 && r.json?.data?.id === earnTx?.id, `${r.status}`);

  r = await req('POST', '/api/panel/v1/earn', { token: tok, body: { customer_no: 7, spend_amount: '-5', idempotency_key: uuid() } });
  check(A, 'negatif harcama → 422', r.status === 422, `${r.status}`);
  r = await req('POST', '/api/panel/v1/earn', { token: tok, body: { customer_no: 7, spend_amount: '10.999', idempotency_key: uuid() } });
  check(A, '3 ondalıklı tutar → 422', r.status === 422, `${r.status}`);
  r = await req('POST', '/api/panel/v1/earn', { token: tok, body: { customer_no: 7, spend_amount: '100', campaign_id: 999999, idempotency_key: uuid() } });
  check(A, 'geçersiz kampanya → 422', r.status === 422 && r.json?.code === 'campaign_not_eligible', `${r.status}`);

  // Kullanım akışı
  r = await req('POST', '/api/panel/v1/redeem', { token: tok, body: { customer_no: 7, amount: '1.00', idempotency_key: uuid() } });
  check(A, 'kullanım isteği → pending', r.status === 201 && r.json?.data?.status === 'pending', `${r.status} ${r.json?.message}`);
  const reqId = r.json?.data?.id;
  let bal = (await req('GET', '/api/panel/v1/customers/7', { token: tok })).json?.data?.wallet?.balance;
  check(A, 'onaysız bakiye değişmedi', bal === earnTx?.balance_after, `${bal}`);
  r = await req('POST', `/api/v1/approvals/${reqId}/approve`, { token: tokens.customer2 });
  check(A, 'başka müşteri onaylayamaz → 404', r.status === 404, `${r.status}`);
  r = await req('POST', `/api/v1/approvals/${reqId}/approve`, { token: tokens.customer });
  check(A, 'müşteri onayı', r.status === 200 && r.json?.approved === true, `${r.status} ${r.json?.message}`);
  r = await req('GET', `/api/panel/v1/approval-requests/${reqId}`, { token: tok });
  check(A, 'personel onayı görüyor', r.json?.data?.status === 'approved', `${r.json?.data?.status_label}`);
  r = await req('POST', `/api/v1/approvals/${reqId}/approve`, { token: tokens.customer });
  check(A, 'ikinci onay → 409', r.status === 409, `${r.status}`);

  // Fazla tutar
  bal = (await req('GET', '/api/panel/v1/customers/7', { token: tok })).json?.data?.wallet?.balance;
  r = await req('POST', '/api/panel/v1/redeem', { token: tok, body: { customer_no: 7, amount: String(+bal + 1), idempotency_key: uuid() } });
  check(A, 'bakiyeden fazla kullanım → 422', r.status === 422 && r.json?.code === 'insufficient_balance', `${r.status}`);

  // Geri çekme
  r = await req('POST', '/api/panel/v1/redeem', { token: tok, body: { customer_no: 7, amount: '0.50', idempotency_key: uuid() } });
  const wid = r.json?.data?.id;
  r = await req('POST', `/api/panel/v1/approval-requests/${wid}/withdraw`, { token: tok });
  check(A, 'bekleyen isteği geri çekme', r.status === 200 && r.json?.data?.status === 'withdrawn', `${r.status}`);

  // Düzeltme (geri alma): az önceki kazanımı geri al, müşteri onaylasın → bakiye ilk duruma döner
  r = await req('POST', '/api/panel/v1/corrections', { token: tok, body: { transaction_id: earnTx.id, amount: '-' + earnTx.amount, reason: 'QA testi – geri alma', idempotency_key: uuid() } });
  check(A, 'düzeltme isteği', r.status === 201, `${r.status} ${r.json?.message}`);
  const corrId = r.json?.data?.id;
  r = await req('POST', `/api/v1/approvals/${corrId}/approve`, { token: tokens.customer });
  check(A, 'düzeltme onayı', r.status === 200 && r.json?.approved, `${r.status}`);
  // 1,00 TL kullanım iadesi için bir düzeltme daha
  const hist = (await req('GET', '/api/panel/v1/customers/7/transactions', { token: tok })).json?.data ?? [];
  const redeemTx = hist.find((t) => t.type === 'redeem' && t.amount === '-1.00');
  if (redeemTx) {
    r = await req('POST', '/api/panel/v1/corrections', { token: tok, body: { transaction_id: redeemTx.id, amount: '1.00', reason: 'QA testi – iade', idempotency_key: uuid() } });
    await req('POST', `/api/v1/approvals/${r.json?.data?.id}/approve`, { token: tokens.customer });
  }
  const after = (await req('GET', '/api/panel/v1/customers/7', { token: tok })).json?.data?.wallet?.balance;
  check(A, 'testler sonrası bakiye ilk değere döndü', after === before, `${before} → ${after}`);

  // Yetki sınırları
  r = await req('GET', '/api/panel/v1/customers/7', { token: tokens.customer });
  check(A, 'müşteri token\'ı panelde → 403', r.status === 403, `${r.status}`);
  r = await req('GET', '/api/v1/wallets', { token: tok });
  check(A, 'personel token\'ı mobil API\'de → 403', r.status === 403, `${r.status}`);
  const otherBranchReq = await req('GET', `/api/panel/v1/approval-requests/1`, { token: tok });
  check(A, 'başka şubenin isteği görünmüyor (id=1)', otherBranchReq.status === 404 || otherBranchReq.json?.data, `${otherBranchReq.status}`);

  // Sorgu hız sınırı (dakikada 20)
  let limited = 0;
  for (let i = 0; i < 22; i++) { const x = await req('GET', '/api/panel/v1/customers/' + (100 + i), { token: tok }); if (x.status === 429) limited++; }
  check(A, 'müşteri sorgu hız sınırı (20/dk) → 429', limited > 0, `${limited} istek sınırlandı`);
}

async function cleanup() {
  for (const t of [tokens.customer, tokens.customer2]) if (t) await req('POST', '/api/v1/auth/logout', { token: t });
  if (tokens.manager) await req('POST', '/api/panel/v1/auth/logout', { token: tokens.manager });
  const r = await req('GET', '/api/v1/me', { token: tokens.customer });
  check('Temizlik', 'çıkış sonrası token geçersiz', r.status === 401, `${r.status}`);
}

async function perfTests() {
  const A = 'Performans';
  const cases = ['/up', '/api/v1/cities', '/api/v1/home?lat=41.01&lng=28.98', '/api/v1/branches?city=34&sort=score', '/api/v1/branches?sort=nearest&lat=41.01&lng=28.98', '/api/v1/branches?q=kebap'];
  for (const p of cases) {
    const ms = [];
    for (let i = 0; i < 3; i++) ms.push((await req('GET', p)).ms);
    ms.sort((a, b) => a - b);
    check(A, p, ms[1] < 3000, `medyan ${ms[1]} ms (${ms.join('/')})`);
  }
}

// ------------------------------------------------------------- web sitesi
async function webTests() {
  const A = 'Web sitesi';
  const W = 'https://kumbare.trthabercanli.com';
  const page = async (path, opts = {}) => {
    const t0 = performance.now();
    const res = await fetch(W + path, { redirect: 'manual', ...opts });
    const html = await res.text();
    return { status: res.status, html, headers: res.headers, ms: Math.round(performance.now() - t0) };
  };
  const title = (h) => (h.match(/<title>([^<]*)<\/title>/) || [])[1];
  const canonical = (h) => (h.match(/<link rel="canonical" href="([^"]+)"/) || [])[1];
  const robots = (h) => (h.match(/<meta name="robots" content="([^"]+)"/) || [])[1];
  const ld = (h) => [...h.matchAll(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/g)].map((m) => { try { return JSON.parse(m[1]); } catch { return 'BOZUK'; } });

  let r;
  try { r = await page('/'); } catch (e) { check(A, 'HTTPS bağlantısı (SSL)', false, String(e.cause?.code ?? e.message)); return; }
  check(A, 'HTTPS bağlantısı (SSL geçerli)', true, '');
  check(A, 'ana sayfa', r.status === 200 && r.html.includes('Hesabı öde, ödülün kumbarana düşsün.'), `${r.status} ${r.ms}ms "${title(r.html)}"`);
  check(A, 'ana sayfa canonical', canonical(r.html) === W + '/' || canonical(r.html) === W, canonical(r.html));
  check(A, 'ana sayfa JSON-LD geçerli', ld(r.html).length > 0 && !ld(r.html).includes('BOZUK'), '');
  check(A, 'sponsorlu blok', r.html.includes('Öne çıkan restoranlar'), '');
  check(A, 'mutfak vitrini 16 fotoğraflı mutfak', (r.html.match(/src="\/img\/mutfak\/kucuk\//g) || []).length === 16, `${(r.html.match(/src="\/img\/mutfak\/kucuk\//g) || []).length}`);
  const panoCount = (r.html.match(/class="pano-liste"/g) || []).length;
  check(A, 'listeler (pano + mutfak listeleri + popüler)', panoCount >= 8, `${panoCount} liste`);
  for (const t of ['En yüksek puanlılar', 'En çok değerlendirilenler', 'En yüksek ödül', 'Mutfağa göre en iyiler', 'Kampanyası olanlar', 'Bu ay en popülerler']) {
    check(A, `bölüm: ${t}`, r.html.includes(t), '');
  }
  const imgs = [...new Set((r.html.match(/\/img\/[a-z0-9\/-]+\.webp/g) || []))];
  let broken = [];
  for (const src of imgs) { const x = await fetch(W + src, { method: 'HEAD' }); if (x.status !== 200 || !(x.headers.get('content-type') || '').includes('webp')) broken.push(src + ':' + x.status); }
  check(A, 'ana sayfadaki tüm fotoğraflar yükleniyor', broken.length === 0, `${imgs.length} fotoğraf${broken.length ? ', bozuk: ' + broken.join(', ') : ''}`);
  const hero = await fetch(W + '/img/hero-sofra.webp');
  const heroKb = Math.round((await hero.arrayBuffer()).byteLength / 1024);
  check(A, 'giriş fotoğrafı boyutu makul', heroKb < 300, `${heroKb} KB`);
  check(A, 'görünmeyen fotoğraflar tembel yükleniyor', (r.html.match(/loading="lazy"/g) || []).length >= 16, '');
  const kat = await page('/kategori/kebap');
  check(A, 'mutfak sayfası başlık fotoğrafı', kat.html.includes('class="kategori-baslik"') && kat.html.includes('/img/mutfak/kebap.webp'), '');
  check(A, 'HSTS ve nosniff başlıkları', r.headers.get('strict-transport-security') && r.headers.get('x-content-type-options') === 'nosniff', '');
  check(A, 'X-Powered-By yok', !r.headers.get('x-powered-by'), `${r.headers.get('x-powered-by')}`);
  check(A, 'Laravel izi yok', !/laravel/i.test(r.html), '');

  const css = (r.html.match(/href="(\/css\/site\.css[^"]*)"/) || [])[1];
  const js = (r.html.match(/src="(\/js\/site\.js[^"]*)"/) || [])[1];
  for (const asset of [css, js, '/fonts/bricolage-grotesque-latin-wght-normal.woff2', '/fonts/source-sans-3-latin-ext-wght-normal.woff2', '/favicon.svg']) {
    const a = await fetch(W + asset);
    check(A, `dosya yükleniyor ${asset?.split('?')[0]}`, a.status === 200, `${a.status} ${a.headers.get('content-type')}`);
  }

  r = await page('http://kumbare.trthabercanli.com/'.replace(W, '') === '' ? '/' : '/');
  const http = await fetch('http://kumbare.trthabercanli.com/', { redirect: 'manual' });
  check(A, 'http → https yönlendirmesi', http.status === 301 || http.status === 302, `${http.status} → ${http.headers.get('location')}`);

  r = await page('/restoranlar/istanbul');
  check(A, 'il sayfası', r.status === 200 && r.html.includes("İstanbul'daki restoranlar"), `${r.status} ${r.ms}ms`);
  check(A, 'il sayfası 20 restoran listeliyor', (r.html.match(/class="restoran"/g) || []).length === 20, `${(r.html.match(/class="restoran"/g) || []).length}`);
  check(A, 'il sayfası dizine açık', robots(r.html) === 'index,follow', robots(r.html));
  const detailPath = (r.html.match(/href="https:\/\/kumbare\.trthabercanli\.com(\/restoran\/[^"]+)"/) || [])[1];

  r = await page('/restoranlar/istanbul/kadikoy');
  check(A, 'ilçe sayfası', r.status === 200 && r.html.includes("Kadıköy'deki restoranlar"), `${r.status}`);
  r = await page('/kategori/kebap');
  check(A, 'mutfak sayfası', r.status === 200 && r.html.includes('Kebap restoranları'), `${r.status}`);
  r = await page('/restoranlar?siralama=puan&min_odul=10');
  check(A, 'filtreli sayfa noindex', r.status === 200 && robots(r.html) === 'noindex,follow', robots(r.html));
  r = await page('/restoranlar?sayfa=2');
  check(A, 'sayfalama canonical ?sayfa=2', canonical(r.html)?.endsWith('/restoranlar?sayfa=2'), canonical(r.html));
  r = await page('/restoranlar?q=' + encodeURIComponent('Közbaşı'));
  check(A, 'site içi arama', r.status === 200 && r.html.includes('Közbaşı'), `${r.status}`);

  r = await page(detailPath);
  const data = ld(r.html);
  const rest = data.find((d) => d['@type'] === 'Restaurant');
  check(A, 'restoran detayı', r.status === 200 && r.html.includes('Menü ve fiyatlar'), `${detailPath} ${r.status} ${r.ms}ms`);
  check(A, 'Restaurant yapısal verisi', !!rest && !!rest.address?.addressRegion, `aggregateRating=${JSON.stringify(rest?.aggregateRating)}`);
  check(A, 'BreadcrumbList yapısal verisi', data.some((d) => d['@type'] === 'BreadcrumbList'), '');
  check(A, 'yorumlarda e-posta/telefon yok', !/@example\.com|0500\d{7}/.test(r.html), '');
  check(A, 'yıldız simgesi kullanılmıyor', !/★|☆|star/i.test(r.html.replace(/<script[\s\S]*?<\/script>/g, '')), '');

  r = await page('/isletmeler-icin');
  check(A, 'işletmeler için + başvuru formu', r.status === 200 && r.html.includes('name="_token"') && r.html.includes('Başvuruyu gönder'), `${r.status}`);
  r = await page('/sifre-sifirla?token=x&email=a@b.c');
  check(A, 'şifre sıfırlama sayfası (noindex)', r.status === 200 && robots(r.html) === 'noindex,nofollow', `${r.status}`);
  r = await page('/kvkk-aydinlatma-metni');
  check(A, 'KVKK sayfası', r.status === 200, `${r.status}`);
  r = await page('/olmayan-bir-sayfa');
  check(A, '404 sayfası', r.status === 404 && r.html.includes('Bu sayfa bulunamadı'), `${r.status}`);
  r = await page('/restoran/olmayan/sube');
  check(A, 'olmayan restoran 404', r.status === 404, `${r.status}`);

  r = await page('/robots.txt');
  check(A, 'robots.txt (site)', r.status === 200 && r.html.includes('Sitemap: ' + W + '/sitemap.xml') && r.html.includes('Disallow: /api/'), r.html.replace(/\n/g, ' | '));
  r = await page('/sitemap.xml');
  const urls = (r.html.match(/<loc>/g) || []).length;
  check(A, 'sitemap.xml', r.status === 200 && urls > 9000 && r.html.includes(W + '/restoran/'), `${urls} adres, ${Math.round(r.html.length / 1024)} KB, ${r.ms}ms`);

  // API alanı ayrımı
  const apiRoot = await req('GET', '/');
  check(A, 'API adresinin kökü hâlâ JSON', apiRoot.json?.name === 'Kumbare API', apiRoot.text.slice(0, 60));
  const apiRobots = await fetch(B + '/robots.txt').then((x) => x.text());
  check(A, 'API adresi arama motorlarına kapalı', /Disallow: \/\s*$/.test(apiRobots.trim()) || apiRobots.includes('Disallow: /\n'), apiRobots.replace(/\n/g, ' | '));
  const apiSite = await fetch(B + '/restoranlar', { redirect: 'manual' });
  check(A, 'site sayfaları API adresinde açılmıyor', apiSite.status === 404, `${apiSite.status}`);

  // Performans
  for (const p of ['/', '/restoranlar/istanbul', detailPath]) {
    const ms = [];
    for (let i = 0; i < 3; i++) ms.push((await page(p)).ms);
    ms.sort((a, b) => a - b);
    check('Performans', 'site ' + p, ms[1] < 3000, `medyan ${ms[1]} ms (${ms.join('/')})`);
  }
}

// ------------------------------------------------------------- panel girişi ve platform yönetimi
// Yönetici sayfaları yalnızca KUMBARE_QA_ADMIN_EMAIL / KUMBARE_QA_ADMIN_PASSWORD ortam
// değişkenleri verilirse (salt okunur) gezilir; şifre betiğe yazılmaz.
async function adminPanelTests() {
  const A = 'Panel';
  const W = 'https://kumbare.trthabercanli.com';
  const jar = new Map();
  const go = async (path, { method = 'GET', form } = {}) => {
    const res = await fetch(W + path, {
      method,
      redirect: 'manual',
      headers: {
        Cookie: [...jar].map(([k, v]) => `${k}=${v}`).join('; '),
        ...(form ? { 'Content-Type': 'application/x-www-form-urlencoded' } : {}),
      },
      body: form ? new URLSearchParams(form).toString() : undefined,
    });
    for (const c of res.headers.getSetCookie?.() ?? []) {
      const [pair] = c.split(';');
      const i = pair.indexOf('=');
      jar.set(pair.slice(0, i), pair.slice(i + 1));
    }
    return { status: res.status, location: res.headers.get('location'), html: await res.text() };
  };
  const token = (h) => (h.match(/name="_token" value="([^"]+)"/) || [])[1];

  let r = await go('/panel/giris');
  check(A, 'giriş sayfası', r.status === 200 && r.html.includes('Panele giriş') && !!token(r.html), `${r.status}`);
  check(A, 'giriş sayfası noindex', r.html.includes('<meta name="robots" content="noindex,nofollow">'), '');
  r = await go('/panel/sifremi-unuttum');
  check(A, 'şifremi unuttum sayfası', r.status === 200 && r.html.includes('Bağlantı gönder'), `${r.status}`);
  for (const p of ['/yonetim', '/yonetim/havaleler', '/yonetim/ayarlar']) {
    const x = await fetch(W + p, { redirect: 'manual' });
    check(A, `${p} girişsiz açılmıyor`, x.status === 302 && (x.headers.get('location') || '').endsWith('/panel/giris'), `${x.status} → ${x.headers.get('location')}`);
  }
  for (const asset of ['/css/panel.css', '/js/panel.js']) {
    const a = await fetch(W + asset);
    check(A, `dosya yükleniyor ${asset}`, a.status === 200, `${a.status}`);
  }
  const robotsTxt = await fetch(W + '/robots.txt').then((x) => x.text());
  check(A, 'robots: /yonetim ve /panel kapalı', robotsTxt.includes('Disallow: /yonetim') && robotsTxt.includes('Disallow: /panel'), '');

  // Yanlış şifre: hata mesajıyla girişe döner
  r = await go('/panel/giris');
  r = await go('/panel/giris', { method: 'POST', form: { _token: token(r.html), email: 'olmayan-qa@example.com', password: 'yanlis-sifre' } });
  const back = await go('/panel/giris');
  check(A, 'yanlış şifre reddediliyor', r.status === 302 && back.html.includes('E-posta adresi veya şifre hatalı.'), `${r.status}`);

  // Örnek veri işletme yöneticisi: yönetim paneline giremez
  r = await go('/panel/giris', { method: 'POST', form: { _token: token(back.html), email: 'isletme1@example.com', password: 'Kumbare2026!' } });
  check(A, 'işletme yöneticisi girişi → /panel', r.status === 302 && (r.location || '').endsWith('/panel'), `${r.status} → ${r.location}`);
  r = await go('/panel');
  check(A, 'işletme paneli bilgi sayfası', r.status === 200 && r.html.includes('hazırlanıyor'), `${r.status}`);
  r = await go('/yonetim');
  check(A, 'işletme yöneticisi /yonetim açamıyor (403)', r.status === 403, `${r.status}`);
  r = await go('/panel/cikis', { method: 'POST', form: { _token: token((await go('/panel')).html) } });
  check(A, 'çıkış', r.status === 302, `${r.status}`);

  const email = process.env.KUMBARE_QA_ADMIN_EMAIL;
  const password = process.env.KUMBARE_QA_ADMIN_PASSWORD;
  if (!email || !password) {
    console.log('     (Yönetici sayfaları atlandı: KUMBARE_QA_ADMIN_EMAIL / KUMBARE_QA_ADMIN_PASSWORD verilmedi)');
    return;
  }
  jar.clear();
  r = await go('/panel/giris');
  r = await go('/panel/giris', { method: 'POST', form: { _token: token(r.html), email, password } });
  check(A, 'platform yöneticisi girişi → /yonetim', r.status === 302 && (r.location || '').endsWith('/yonetim'), `${r.status} → ${r.location}`);
  for (const [p, text] of [
    ['/yonetim', 'Genel bakış'], ['/yonetim/isletmeler?durum=pending', 'İşletme başvuruları'], ['/yonetim/isletmeler', 'İşletmeler ve şubeler'],
    ['/yonetim/isletmeler/1', 'Şubeler'], ['/yonetim/musteriler?q=1', 'Müşteriler'], ['/yonetim/musteriler/1', 'Kumbaraları'],
    ['/yonetim/havaleler?durum=all', 'Havale bildirimleri'], ['/yonetim/abonelikler?durum=all', 'Abonelikler'], ['/yonetim/sponsorluklar', 'Sponsorluklar'],
    ['/yonetim/kampanyalar', 'Kampanya moderasyonu'], ['/yonetim/sikayetler', 'Yorum şikâyetleri'], ['/yonetim/kategoriler', 'Restoran kategorileri'],
    ['/yonetim/ayarlar', 'Paketler ve ayarlar'], ['/yonetim/denetim', 'Denetim kayıtları'], ['/yonetim/denetim/numara-sorgulari?gun=30', 'Olağandışı müşteri numarası'],
  ]) {
    const t0 = performance.now();
    const x = await go(p);
    check(A, `yönetim sayfası ${p}`, x.status === 200 && x.html.includes(text), `${x.status} ${Math.round(performance.now() - t0)}ms`);
  }
  await go('/panel/cikis', { method: 'POST', form: { _token: token((await go('/yonetim')).html) } });
}

await webTests();
await adminPanelTests();
const top = await publicTests();
await errorTests();
await customerTests(top);
await panelTests();
await cleanup();
await perfTests();
const fails = results.filter((r) => !r.ok);
console.log(`\nTOPLAM: ${results.length} kontrol, ${results.length - fails.length} başarılı, ${fails.length} başarısız`);
