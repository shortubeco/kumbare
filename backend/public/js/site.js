// Kumbare – küçük etkileşimler. Sayfalar JavaScript olmadan da çalışır.
(function () {
    'use strict';

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
