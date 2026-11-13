// Kumbare – küçük etkileşimler. Sayfalar JavaScript olmadan da çalışır.
(function () {
    'use strict';

    // ---- Telefon menüsü: soldan kayan çekmece -----------------------------
    // Kapanır: ✕, karartılmış alana dokunma, Esc, telefonun geri tuşu, menüden bir bağlantı.
    var menuAc = document.querySelector('[data-menu-ac]');
    var menu = menuAc && document.getElementById(menuAc.getAttribute('aria-controls'));
    if (menu) {
        var menuKapat = menu.querySelector('[data-menu-kapat]');
        var perde = document.querySelector('[data-menu-perde]');
        var gecmisteVar = false; // geri tuşu için geçmişe eklenen kayıt

        var acikMi = function () { return document.body.classList.contains('menu-acik'); };
        var gizle = function (odakDon) {
            if (!acikMi()) return;
            document.body.classList.remove('menu-acik');
            menuAc.setAttribute('aria-expanded', 'false');
            if (odakDon) menuAc.focus();
        };
        var kapat = function (odakDon) {
            gizle(odakDon);
            if (gecmisteVar) { gecmisteVar = false; history.back(); }
        };
        var ac = function () {
            document.body.classList.add('menu-acik');
            menuAc.setAttribute('aria-expanded', 'true');
            try { history.pushState({ kumbareMenu: true }, ''); gecmisteVar = true; } catch (e) { gecmisteVar = false; }
            setTimeout(function () { menuKapat.focus(); }, 50);
        };

        menuAc.addEventListener('click', function () { if (acikMi()) kapat(true); else ac(); });
        menuKapat.addEventListener('click', function () { kapat(true); });
        if (perde) perde.addEventListener('click', function () { kapat(true); });
        window.addEventListener('popstate', function () { gecmisteVar = false; gizle(true); });

        // Menüden bir bağlantıya dokunulunca yalnızca kapat; sayfa geçişi geçmişi zaten değiştirir.
        menu.querySelectorAll('a').forEach(function (a) {
            a.addEventListener('click', function () {
                gizle(false);
                if (gecmisteVar) { gecmisteVar = false; try { history.replaceState(null, ''); } catch (e) { /* yoksay */ } }
            });
        });

        document.addEventListener('keydown', function (e) {
            if (!acikMi()) return;
            if (e.key === 'Escape') { e.preventDefault(); kapat(true); return; }
            if (e.key !== 'Tab') return;
            // Odak menünün içinde döner.
            var odaklar = menu.querySelectorAll('a, button');
            var ilk = odaklar[0], son = odaklar[odaklar.length - 1];
            if (e.shiftKey && document.activeElement === ilk) { e.preventDefault(); son.focus(); }
            else if (!e.shiftKey && document.activeElement === son) { e.preventDefault(); ilk.focus(); }
        });

        // Ekran genişleyip masaüstü menüsüne geçilirse çekmeceyi kapat.
        var genis = window.matchMedia('(min-width: 721px)');
        var genisDegisti = function () { if (genis.matches) kapat(false); };
        if (genis.addEventListener) genis.addEventListener('change', genisDegisti); else if (genis.addListener) genis.addListener(genisDegisti);
    }

    // ---- Adisyon: ödül hesaplayıcı ---------------------------------------
    var stage = document.querySelector('[data-adisyon]');
    if (stage) {
        var input = stage.querySelector('#adisyon-tutar');
        var output = stage.querySelector('#adisyon-sonuc');
        var buttons = stage.querySelectorAll('[data-oran]');
        var coin = stage.querySelector('.coin');
        var rate = 10;

        var parse = function (text) {
            // "1.234,50" veya "1234.5" → kuruş
            var clean = String(text).replace(/[^\d.,]/g, '');
            if (clean.indexOf(',') > -1) clean = clean.replace(/\./g, '').replace(',', '.');
            var value = parseFloat(clean);
            return isNaN(value) ? 0 : Math.round(value * 100);
        };
        var format = function (cents) {
            return (cents / 100).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' TL';
        };
        var drop = function () {
            if (!coin) return;
            coin.classList.remove('drop');
            void coin.offsetWidth; // animasyonu yeniden başlat
            coin.classList.add('drop');
        };
        var update = function (animate) {
            var cents = parse(input.value);
            var reward = Math.floor((cents * rate * 100 + 5000) / 10000);
            output.textContent = format(reward);
            if (animate) drop();
        };

        input.addEventListener('input', function () { update(false); });
        input.addEventListener('change', function () {
            var cents = parse(input.value);
            input.value = (cents / 100).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            update(true);
        });
        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                rate = parseInt(btn.getAttribute('data-oran'), 10);
                buttons.forEach(function (b) { b.setAttribute('aria-pressed', b === btn ? 'true' : 'false'); });
                update(true);
            });
        });
        // Sayfa açılışındaki tek hareket
        window.setTimeout(drop, 600);
    }

    // ---- Yakınımdaki restoranlar ------------------------------------------
    document.querySelectorAll('[data-yakinimdaki]').forEach(function (link) {
        if (!('geolocation' in navigator)) { link.hidden = true; return; }
        link.addEventListener('click', function (e) {
            e.preventDefault();
            link.textContent = 'Konumun alınıyor…';
            navigator.geolocation.getCurrentPosition(function (pos) {
                window.location.href = '/restoranlar?siralama=yakin&lat=' + pos.coords.latitude.toFixed(4) + '&lng=' + pos.coords.longitude.toFixed(4);
            }, function () {
                link.textContent = 'Konum izni verilmedi. Şehir seçerek arayabilirsin.';
            }, { timeout: 10000, maximumAge: 600000 });
        });
    });

    // ---- Sıralama seçilince hemen uygula ----------------------------------
    document.querySelectorAll('[data-siralama-secim]').forEach(function (select) {
        select.addEventListener('change', function () {
            if (select.value === 'yakin' && 'geolocation' in navigator) {
                navigator.geolocation.getCurrentPosition(function (pos) {
                    var form = select.form;
                    ['lat', 'lng'].forEach(function (k) {
                        var i = document.createElement('input');
                        i.type = 'hidden'; i.name = k;
                        i.value = (k === 'lat' ? pos.coords.latitude : pos.coords.longitude).toFixed(4);
                        form.appendChild(i);
                    });
                    form.submit();
                }, function () { select.value = 'populer'; });
                return;
            }
            select.form.submit();
        });
    });

    // ---- Filtre paneli (mobil) ve okunaklı adresler -----------------------
    document.querySelectorAll('[data-filtre]').forEach(function (form) {
        var toggle = form.querySelector('[data-filtre-ac]');
        if (toggle) {
            toggle.addEventListener('click', function () {
                var collapsed = form.getAttribute('data-collapsed') === 'true';
                form.setAttribute('data-collapsed', collapsed ? 'false' : 'true');
                toggle.setAttribute('aria-expanded', collapsed ? 'true' : 'false');
                toggle.textContent = collapsed ? 'Filtreleri gizle' : 'Filtreleri göster';
            });
        }

        var ilSelect = form.querySelector('[data-il-secim]');
        if (ilSelect) {
            // İl değişince ilçe seçimi sıfırlanır.
            ilSelect.addEventListener('change', function () {
                var ilce = form.querySelector('[name="ilce"]');
                if (ilce) ilce.value = '';
            });
        }

        // İl/ilçe seçiliyse /restoranlar/{il}/{ilce} adresine git; boş alanları gönderme.
        form.addEventListener('submit', function (e) {
            if (!/\/restoranlar(\/|$)/.test(form.getAttribute('action'))) return;
            e.preventDefault();
            var data = new FormData(form);
            var il = data.get('il'), ilce = data.get('ilce');
            var path = '/restoranlar' + (il ? '/' + encodeURIComponent(il) + (ilce ? '/' + encodeURIComponent(ilce) : '') : '');
            var params = new URLSearchParams();
            data.forEach(function (value, key) {
                if (key === 'il' || key === 'ilce' || value === '' || (key === 'siralama' && value === 'populer')) return;
                params.append(key, value);
            });
            var qs = params.toString();
            window.location.href = path + (qs ? '?' + qs : '');
        });
    });
})();
