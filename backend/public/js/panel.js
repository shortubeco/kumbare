// Kumbare paneli: küçük yardımcılar (JavaScript olmadan da sayfalar çalışır).
(function () {
    // Telefon görünümünde menüyü aç/kapat
    var side = document.querySelector('.p-side');
    var toggle = document.querySelector('.p-menu-toggle');
    if (side && toggle) {
        toggle.addEventListener('click', function () {
            var open = side.classList.toggle('open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    // Aynı anda tek bir açılır işlem formu açık kalsın; dışarı tıklayınca kapansın
    document.addEventListener('toggle', function (e) {
        var el = e.target;
        if (!(el instanceof HTMLDetailsElement) || !el.classList.contains('act') || !el.open) return;
        document.querySelectorAll('details.act[open]').forEach(function (d) { if (d !== el) d.open = false; });
        var first = el.querySelector('textarea, input:not([type=hidden]), select');
        if (first) first.focus();
    }, true);
    document.addEventListener('click', function (e) {
        document.querySelectorAll('details.act[open]').forEach(function (d) {
            if (!d.contains(e.target)) d.open = false;
        });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') document.querySelectorAll('details.act[open]').forEach(function (d) { d.open = false; });
    });

    // Grafik açılır kutusu: sütunun üzerine gelince / klavyeyle odaklanınca değerleri gösterir
    var tip = null;
    function showTip(target, x, y) {
        var data;
        try { data = JSON.parse(target.getAttribute('data-tip')); } catch (err) { return; }
        if (!tip) { tip = document.createElement('div'); tip.className = 'chart-tip'; tip.setAttribute('role', 'status'); document.body.appendChild(tip); }
        tip.textContent = '';
        var h = document.createElement('div'); h.className = 'h'; h.textContent = data[0]; tip.appendChild(h);
        data.slice(1).forEach(function (row) {
            var r = document.createElement('div'); r.className = 'r';
            var key = document.createElement('i'); key.style.background = row[2] || 'transparent'; r.appendChild(key);
            var v = document.createElement('b'); v.textContent = row[1]; r.appendChild(v);
            var n = document.createElement('span'); n.textContent = row[0]; r.appendChild(n);
            tip.appendChild(r);
        });
        tip.style.display = 'block';
        var w = tip.offsetWidth, hh = tip.offsetHeight;
        var left = Math.min(x + 14, window.innerWidth - w - 8), top = y - hh - 12;
        if (top < 8) top = y + 16;
        tip.style.left = Math.max(8, left) + 'px'; tip.style.top = top + 'px';
    }
    function hideTip() { if (tip) tip.style.display = 'none'; }
    document.addEventListener('pointermove', function (e) {
        var t = e.target.closest && e.target.closest('.chart .hit');
        if (t) showTip(t, e.clientX, e.clientY); else hideTip();
    });
    document.addEventListener('focusin', function (e) {
        if (e.target.classList && e.target.classList.contains('hit')) {
            var b = e.target.getBoundingClientRect();
            showTip(e.target, b.left + b.width / 2, b.top + 20);
        }
    });
    document.addEventListener('focusout', hideTip);
    window.addEventListener('scroll', hideTip, { passive: true });

    // "Kopyala" düğmeleri (IBAN vb.)
    document.addEventListener('click', function (e) {
        var b = e.target.closest && e.target.closest('[data-copy]');
        if (!b || !navigator.clipboard) return;
        navigator.clipboard.writeText(b.getAttribute('data-copy')).then(function () {
            var old = b.textContent; b.textContent = 'Kopyalandı';
            setTimeout(function () { b.textContent = old; }, 1500);
        });
    });

    // Havale formu: amaç / dönem / ay seçilince ilgili alanları göster, beklenen tutarı yaz
    var pf = document.getElementById('havale-formu');
    if (pf) {
        var sync = function () {
            var purpose = (pf.querySelector('[name=purpose]:checked') || {}).value;
            pf.querySelectorAll('[data-show]').forEach(function (el) { el.hidden = el.getAttribute('data-show') !== purpose; });
            var key = purpose === 'sponsorship' ? 'sponsorship' : ((pf.querySelector('[name=billing_cycle]:checked') || {}).value || 'monthly');
            var price = pf.getAttribute('data-price-' + key);
            var hint = document.getElementById('beklenen');
            if (hint) hint.textContent = price ? 'Beklenen tutar: ' + price : 'Bu ödeme için fiyat henüz belirlenmedi; yatırdığınız tutarı yazın.';
            var amount = pf.querySelector('[name=amount]');
            if (amount && price && (!amount.value || amount.dataset.auto === '1')) { amount.value = price.replace(' TL', ''); amount.dataset.auto = '1'; }
        };
        pf.addEventListener('change', function (e) { if (e.target.name === 'amount') e.target.dataset.auto = ''; sync(); });
        sync();
    }

    // Çift tıklamayla aynı formun iki kez gönderilmesini engelle
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (form.hasAttribute('data-no-guard')) return; // sayfa yenilemeden tekrar tekrar gönderilen formlar
        if (form.dataset.sent) { e.preventDefault(); return; }
        form.dataset.sent = '1';
        // Devre dışı düğmenin değeri gönderilmez; basılan düğmenin adı/değeri gizli alanla korunur
        var sub = e.submitter;
        if (sub && sub.name) {
            var h = document.createElement('input');
            h.type = 'hidden'; h.name = sub.name; h.value = sub.value;
            form.appendChild(h);
        }
        form.querySelectorAll('button[type=submit], button:not([type])').forEach(function (b) { b.disabled = true; });
    });
})();
