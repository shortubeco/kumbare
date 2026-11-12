// Kumbare – kasa işlem ekranı.
// Akış: müşteri numarası → (Ödül kazandır: tutar + kampanya → özet → Onayla)
//                         → (Ödül kullandır: tutar → istek → müşteri onayı beklenir → sonuç)
// Kişi adları gibi metinler her zaman textContent ile yazılır.
(function () {
    'use strict';

    var root = document.getElementById('tx');
    if (!root) return;

    var csrf = (document.querySelector('meta[name=csrf-token]') || {}).content || '';
    var $ = function (id) { return document.getElementById(id); };
    var show = function (el, on) { if (el) el.hidden = !on; };
    var fmtMoney = function (v) {
        return new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(v || 0)) + ' TL';
    };
    var fmtRate = function (v) {
        return '%' + String(Number(v)).replace('.', ',');
    };
    // "1.234,50" / "1234.5" / "1234,5" → "1234.50"; geçersizse null
    var parseMoney = function (s) {
        s = String(s || '').replace(/\s|TL|₺/g, '');
        if (!s) return null;
        if (s.indexOf(',') !== -1) s = s.replace(/\./g, '').replace(',', '.');
        if (!/^\d{1,10}(\.\d{1,2})?$/.test(s)) return null;
        return Number(s) > 0 ? Number(s).toFixed(2) : null;
    };
    var uuid = function () {
        if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
    };

    async function api(method, url, body) {
        var res;
        try {
            res = await fetch(url, {
                method: method,
                headers: Object.assign({ 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body ? { 'Content-Type': 'application/json' } : {}),
                body: body ? JSON.stringify(body) : undefined,
                credentials: 'same-origin',
            });
        } catch (e) {
            throw { message: 'Sunucuya ulaşılamadı. İnternet bağlantınızı kontrol edip tekrar deneyin.', network: true };
        }
        var data = null;
        try { data = await res.json(); } catch (e) { /* boş gövde */ }
        if (res.status === 419) throw { message: 'Oturum süresi doldu. Sayfayı yenileyip tekrar deneyin.' };
        if (res.status === 401) { window.location.href = '/panel/giris'; throw { message: 'Oturumunuz kapanmış.' }; }
        if (res.status === 429) throw { message: 'Çok fazla sorgu yapıldı. Lütfen bir dakika bekleyin.' };
        if (!res.ok) throw { message: (data && data.message) || 'İşlem yapılamadı.', code: data && data.code, data: data };
        return data;
    }

    var state = { no: null, customer: null, wallet: null, earnKey: null, redeemKey: null, requestId: null, poll: null };
    var errorBox = $('tx-error');
    function error(msg) { errorBox.textContent = msg || ''; show(errorBox, !!msg); }

    function resetAll(keepCustomer) {
        stopPoll();
        ['tx-earn', 'tx-redeem', 'tx-result', 'tx-earn-summary', 'tx-limit', 'tx-wait'].forEach(function (id) { show($(id), false); });
        show($('tx-redeem-form'), true);
        $('tx-spend').value = ''; $('tx-amount').value = '';
        state.earnKey = null; state.redeemKey = null; state.requestId = null;
        error('');
        if (!keepCustomer) {
            state.no = null; state.customer = null; state.wallet = null;
            show($('tx-customer'), false);
            $('tx-no').value = '';
            $('tx-no').focus();
        } else if (state.no) {
            find(state.no, true);
        }
    }

    // ---------------------------------------------------------- 1. Müşteri bul
    async function find(no, quiet) {
        error('');
        if (!/^\d{1,12}$/.test(no)) { error('Müşteri numarası yalnızca rakamlardan oluşur.'); return; }
        try {
            var res = await api('GET', root.dataset.lookup.replace('__NO__', no));
            var d = res.data;
            state.no = no; state.customer = d.customer; state.wallet = d.wallet; state.counter = d.monthly_counter;
            $('tx-name').textContent = d.customer.full_name;
            $('tx-cno').textContent = '#' + d.customer.customer_no;
            show($('tx-bday'), !!d.customer.is_birthday_today);
            $('tx-pending').textContent = d.pending_requests ? (d.pending_requests + ' onay bekleyen istek') : '';
            show($('tx-pending'), d.pending_requests > 0);
            $('tx-balance').textContent = fmtMoney(d.wallet.balance);
            show($('tx-customer'), true);
            if (!quiet) {
                show($('tx-earn'), false); show($('tx-redeem'), false); show($('tx-result'), false);
                var first = $('tx-go-earn') || $('tx-go-redeem');
                if (first) first.focus();
            }
        } catch (e) {
            show($('tx-customer'), false);
            error(e.message);
        }
    }

    $('tx-find').addEventListener('submit', function (e) {
        e.preventDefault();
        var no = $('tx-no').value.trim(); // önce oku: resetAll alanı temizler
        resetAll(false);
        $('tx-no').value = no;
        find(no);
    });
    $('tx-reset').addEventListener('click', function () { resetAll(false); });
    document.querySelectorAll('[data-cancel]').forEach(function (b) { b.addEventListener('click', function () { resetAll(true); }); });
    $('tx-next').addEventListener('click', function () { resetAll(false); });
    $('tx-same').addEventListener('click', function () { resetAll(true); });

    // ---------------------------------------------------------- 2a. Ödül kazandır
    var previewTimer = null;
    var goEarn = $('tx-go-earn');
    if (goEarn) goEarn.addEventListener('click', async function () {
        show($('tx-redeem'), false); show($('tx-result'), false);
        show($('tx-earn'), true);
        $('tx-spend').value = ''; show($('tx-earn-summary'), false); show($('tx-limit'), false);
        $('tx-earn-confirm').disabled = true;
        state.earnKey = null;
        var box = $('tx-campaigns');
        box.textContent = 'Kampanyalar yükleniyor…';
        try {
            var res = await api('GET', root.dataset.campaigns.replace('__NO__', state.no));
            box.textContent = '';
            var opts = [{ id: '', title: 'Kampanya yok', rate: res.default_rate }].concat(
                (res.data || []).filter(function (c) { return c.reward_rate !== null; })
                    .map(function (c) { return { id: String(c.id), title: c.title, rate: c.reward_rate, cond: (c.conditions || []).join(' · ') }; })
            );
            opts.forEach(function (o, i) {
                var label = document.createElement('label');
                var input = document.createElement('input');
                input.type = 'radio'; input.name = 'campaign'; input.value = o.id; input.checked = i === 0;
                var t = document.createElement('span'); t.textContent = o.title;
                if (o.cond) { var s = document.createElement('small'); s.className = 'muted'; s.textContent = ' — ' + o.cond; t.appendChild(s); }
                var r = document.createElement('span'); r.className = 'rate'; r.textContent = fmtRate(o.rate);
                label.appendChild(input); label.appendChild(t); label.appendChild(r);
                box.appendChild(label);
            });
        } catch (e) {
            box.textContent = '';
            error(e.message);
        }
        $('tx-spend').focus();
    });

    function schedulePreview() {
        clearTimeout(previewTimer);
        state.earnKey = null; // tutar/kampanya değişti → yeni işlem anahtarı
        $('tx-earn-confirm').disabled = true;
        previewTimer = setTimeout(preview, 350);
    }
    $('tx-spend').addEventListener('input', schedulePreview);
    $('tx-campaigns').addEventListener('change', schedulePreview);
    $('tx-spend').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); if (!$('tx-earn-confirm').disabled) $('tx-earn-confirm').click(); }
    });

    async function preview() {
        var spend = parseMoney($('tx-spend').value);
        var sum = $('tx-earn-summary');
        show($('tx-limit'), false);
        if (!spend) { show(sum, false); return; }
        var campaign = (document.querySelector('#tx-campaigns input:checked') || {}).value || null;
        try {
            var res = await api('POST', root.dataset.preview, { customer_no: Number(state.no), spend_amount: spend, campaign_id: campaign ? Number(campaign) : null });
            var d = res.data;
            if (parseMoney($('tx-spend').value) !== spend) return; // bu arada değişti
            sum.textContent = '';
            var line = function (k, v, cls) {
                var div = document.createElement('div'); div.className = 'line' + (cls ? ' ' + cls : '');
                var a = document.createElement('span'); a.textContent = k;
                var b = document.createElement('b'); b.textContent = v;
                div.appendChild(a); div.appendChild(b); sum.appendChild(div);
            };
            line(d.customer.full_name + ' (#' + d.customer.customer_no + ')', '');
            line('Harcama × oran', fmtMoney(d.spend_amount) + ' × ' + fmtRate(d.reward_rate) + (d.campaign ? ' (' + d.campaign.title + ')' : ''));
            line('Kazanılacak ödül', fmtMoney(d.reward_amount), 'total');
            line('Yeni bakiye', fmtMoney(d.new_balance));
            show(sum, true);
            if (!d.can_earn) {
                var lim = $('tx-limit');
                lim.textContent = 'Bu ayın ücretsiz müşteri limiti doldu; bu müşteri bu ay ilk kez geldiği için ödül kazandırılamaz. ';
                if (root.dataset.upgrade) {
                    var a = document.createElement('a'); a.href = root.dataset.upgrade; a.textContent = 'Profesyonel pakete geç';
                    lim.appendChild(a);
                } else {
                    lim.appendChild(document.createTextNode('Şube yöneticinize bildirin.'));
                }
                show(lim, true);
                $('tx-earn-confirm').disabled = true;
            } else {
                state.earnKey = state.earnKey || uuid();
                $('tx-earn-confirm').disabled = false;
            }
            error('');
        } catch (e) {
            show(sum, false);
            error(e.message);
        }
    }

    $('tx-earn-confirm').addEventListener('click', async function () {
        var btn = this;
        var spend = parseMoney($('tx-spend').value);
        if (!spend || !state.earnKey) return;
        var campaign = (document.querySelector('#tx-campaigns input:checked') || {}).value || null;
        btn.disabled = true; btn.textContent = 'Kaydediliyor…';
        try {
            // Aynı anahtar: bağlantı koparsa ve tekrar basılırsa ikinci kez kaydedilmez
            var res = await api('POST', root.dataset.earn, { customer_no: Number(state.no), spend_amount: spend, campaign_id: campaign ? Number(campaign) : null, idempotency_key: state.earnKey });
            var tx = res.data;
            result('ok', 'Ödül kumbaraya eklendi', fmtMoney(tx.amount),
                state.customer.full_name + ' · Yeni bakiye: ' + fmtMoney(tx.balance_after));
            state.earnKey = null;
        } catch (e) {
            error(e.message);
            btn.disabled = !!e.network ? false : true;
            if (!e.network) state.earnKey = null;
        } finally {
            btn.textContent = 'Onayla';
        }
    });

    // ---------------------------------------------------------- 2b. Ödül kullandır
    var goRedeem = $('tx-go-redeem');
    if (goRedeem) goRedeem.addEventListener('click', function () {
        show($('tx-earn'), false); show($('tx-result'), false);
        show($('tx-redeem'), true); show($('tx-redeem-form'), true); show($('tx-wait'), false);
        $('tx-amount').value = '';
        state.redeemKey = null;
        $('tx-amount-hint').textContent = 'En fazla ' + fmtMoney(state.wallet.balance) + ' (müşterinin bu şubedeki bakiyesi)';
        $('tx-redeem-send').disabled = Number(state.wallet.balance) <= 0;
        if (Number(state.wallet.balance) <= 0) error('Müşterinin bu şubede kullanılabilir bakiyesi yok.');
        $('tx-amount').focus();
    });
    $('tx-amount').addEventListener('input', function () { state.redeemKey = null; });
    $('tx-amount').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); $('tx-redeem-send').click(); } });

    $('tx-redeem-send').addEventListener('click', async function () {
        var btn = this;
        var amount = parseMoney($('tx-amount').value);
        error('');
        if (!amount) { error('Geçerli bir tutar girin (örn. 250,00).'); return; }
        if (Number(amount) > Number(state.wallet.balance)) { error('Tutar bakiyeyi (' + fmtMoney(state.wallet.balance) + ') aşamaz.'); return; }
        state.redeemKey = state.redeemKey || uuid();
        btn.disabled = true;
        try {
            var res = await api('POST', root.dataset.redeem, { customer_no: Number(state.no), amount: amount, idempotency_key: state.redeemKey });
            state.requestId = res.data.id;
            show($('tx-redeem-form'), false);
            show($('tx-wait'), true);
            startPoll();
        } catch (e) {
            error(e.message);
            if (!e.network) state.redeemKey = null;
        } finally {
            btn.disabled = false;
        }
    });

    function startPoll() {
        stopPoll();
        var started = Date.now();
        var tick = async function () {
            if (!state.requestId) return;
            await checkRequest();
            // İlk 2 dakika 3 sn'de bir, sonra 10 sn'de bir; 30 dk sonra otomatik sorgu durur
            var elapsed = Date.now() - started;
            if (state.requestId && elapsed < 30 * 60 * 1000) state.poll = setTimeout(tick, elapsed < 120000 ? 3000 : 10000);
        };
        state.poll = setTimeout(tick, 3000);
    }
    function stopPoll() { if (state.poll) clearTimeout(state.poll); state.poll = null; }

    async function checkRequest() {
        try {
            var res = await api('GET', root.dataset.request.replace('__ID__', state.requestId));
            var r = res.data;
            if (r.status === 'pending') return;
            stopPoll();
            var id = state.requestId; state.requestId = null;
            if (r.status === 'approved') {
                result('ok', 'Müşteri onayladı — indirimi uygulayın', fmtMoney(r.amount),
                    r.customer.full_name + ' · Kalan bakiye: ' + fmtMoney(r.balance_after));
            } else if (r.status === 'rejected') {
                result('bad', 'İstek onaylanmadı', 'İndirim uygulamayın', r.status_label);
            } else {
                result('neutral', 'İstek geri çekildi', 'İndirim uygulanmadı', '#' + id);
            }
        } catch (e) {
            if (!e.network) error(e.message);
        }
    }
    $('tx-refresh').addEventListener('click', checkRequest);
    $('tx-withdraw').addEventListener('click', async function () {
        if (!state.requestId) return;
        var btn = this; btn.disabled = true;
        try {
            await api('POST', root.dataset.withdraw.replace('__ID__', state.requestId));
            await checkRequest();
        } catch (e) {
            error(e.message);
            await checkRequest(); // bu arada onaylanmış olabilir
        } finally {
            btn.disabled = false;
        }
    });

    // ---------------------------------------------------------- 3. Sonuç
    function result(kind, title, big, sub) {
        show($('tx-earn'), false); show($('tx-redeem'), false);
        var box = $('tx-result-box');
        box.textContent = '';
        var div = document.createElement('div'); div.className = 'tx-big ' + kind; div.setAttribute('role', 'status');
        var t = document.createElement('div'); t.textContent = title;
        var b = document.createElement('b'); b.textContent = big;
        var s = document.createElement('div'); s.textContent = sub || '';
        div.appendChild(t); div.appendChild(b); div.appendChild(s);
        box.appendChild(div);
        show($('tx-result'), true);
        $('tx-next').focus();
        // Bakiyeyi kartta güncelle
        if (state.no) find(state.no, true);
    }

    // Sayfadan ayrılırken bekleyen istek varsa uyar (sorgu sayfada kaldıkça sürer)
    window.addEventListener('beforeunload', function (e) {
        if (state.requestId) { e.preventDefault(); e.returnValue = ''; }
    });
})();
