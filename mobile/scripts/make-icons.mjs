// Kumbare simgelerini üretir (ek paket gerektirmez): mürekkep zemin üzerinde sarı kumbara ve para yuvası.
// Çalıştırma: node scripts/make-icons.mjs
import { mkdirSync, writeFileSync } from 'node:fs';
import { deflateSync } from 'node:zlib';

const INK = [0x0e, 0x3b, 0x3a];
const GOLD = [0xf2, 0xb7, 0x05];

// --- PNG yazıcı (RGBA, 8 bit) ---
const CRC = new Int32Array(256).map((_, n) => {
  let c = n;
  for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
  return c;
});
function crc32(buf) {
  let c = -1;
  for (const b of buf) c = CRC[(c ^ b) & 0xff] ^ (c >>> 8);
  return (c ^ -1) >>> 0;
}
function chunk(type, data) {
  const len = Buffer.alloc(4);
  len.writeUInt32BE(data.length);
  const td = Buffer.concat([Buffer.from(type), data]);
  const crc = Buffer.alloc(4);
  crc.writeUInt32BE(crc32(td));
  return Buffer.concat([len, td, crc]);
}
function png(size, px) {
  const raw = Buffer.alloc((size * 4 + 1) * size);
  for (let y = 0; y < size; y++) {
    raw[y * (size * 4 + 1)] = 0;
    px.copy(raw, y * (size * 4 + 1) + 1, y * size * 4, (y + 1) * size * 4);
  }
  const ihdr = Buffer.alloc(13);
  ihdr.writeUInt32BE(size, 0);
  ihdr.writeUInt32BE(size, 4);
  ihdr[8] = 8;
  ihdr[9] = 6;
  return Buffer.concat([Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]), chunk('IHDR', ihdr), chunk('IDAT', deflateSync(raw, { level: 9 })), chunk('IEND', Buffer.alloc(0))]);
}

// --- Şekiller (0..1 koordinat), yuvarlatılmış dikdörtgen içinde mi? ---
function inRoundRect(x, y, cx, cy, w, h, r) {
  const dx = Math.max(Math.abs(x - cx) - (w / 2 - r), 0);
  const dy = Math.max(Math.abs(y - cy) - (h / 2 - r), 0);
  return dx * dx + dy * dy <= r * r;
}

/**
 * opts.bg: zemin rengi ya da null (saydam)
 * opts.scale: kumbaranın simgeye oranı (maskable / adaptive için küçük)
 * opts.mono: tek renk (Android monokrom simge) — kumbara beyaz, yuva saydam
 */
function render(size, { bg = INK, scale = 0.6, mono = false, roundBg = 0 } = {}) {
  const px = Buffer.alloc(size * size * 4);
  const S = 4; // kenar yumuşatma için alt örnekleme
  const box = scale; // kumbara kutusunun kenarı
  const r = box * 0.24;
  const slotW = box * 0.56;
  const slotH = box * 0.13;
  const cy = 0.5 + box * 0.04;
  const slotY = cy - box * 0.12;
  for (let y = 0; y < size; y++) {
    for (let x = 0; x < size; x++) {
      let acc = [0, 0, 0, 0];
      for (let sy = 0; sy < S; sy++) {
        for (let sx = 0; sx < S; sx++) {
          const u = (x + (sx + 0.5) / S) / size;
          const v = (y + (sy + 0.5) / S) / size;
          let c = null;
          let a = 0;
          if (bg && (roundBg === 0 || inRoundRect(u, v, 0.5, 0.5, 1, 1, roundBg))) {
            c = bg;
            a = 1;
          }
          if (inRoundRect(u, v, 0.5, cy, box, box, r)) {
            if (inRoundRect(u, v, 0.5, slotY, slotW, slotH, slotH / 2)) {
              if (mono) {
                c = null;
                a = 0;
              } else {
                c = INK;
                a = 1;
              }
            } else {
              c = mono ? [255, 255, 255] : GOLD;
              a = 1;
            }
          }
          if (a) {
            acc[0] += c[0];
            acc[1] += c[1];
            acc[2] += c[2];
            acc[3] += 255;
          }
        }
      }
      const n = S * S;
      const i = (y * size + x) * 4;
      const cov = acc[3] / 255;
      px[i] = cov ? Math.round(acc[0] / cov) : 0;
      px[i + 1] = cov ? Math.round(acc[1] / cov) : 0;
      px[i + 2] = cov ? Math.round(acc[2] / cov) : 0;
      px[i + 3] = Math.round(acc[3] / n);
    }
  }
  return png(size, px);
}

const out = (path, buf) => {
  writeFileSync(path, buf);
  console.log('yazıldı:', path);
};

mkdirSync('public/icons', { recursive: true });
// Web / PWA
out('public/icons/icon-192.png', render(192));
out('public/icons/icon-512.png', render(512));
out('public/icons/maskable-512.png', render(512, { scale: 0.46 }));
out('public/icons/apple-touch-icon.png', render(180));
out('public/icons/favicon-32.png', render(32, { scale: 0.7, roundBg: 0.22 }));
out('public/favicon.png', render(48, { scale: 0.7, roundBg: 0.22 }));
// Uygulama (Android APK) — app.json bu dosyaları kullanır
out('assets/images/icon.png', render(1024));
out('assets/images/android-icon-foreground.png', render(1024, { bg: null, scale: 0.4 }));
out('assets/images/android-icon-background.png', render(1024, { scale: 0 }));
out('assets/images/android-icon-monochrome.png', render(1024, { bg: null, scale: 0.4, mono: true }));
out('assets/images/splash-icon.png', render(512, { bg: null, scale: 0.8 }));
out('assets/images/favicon.png', render(48, { scale: 0.7, roundBg: 0.22 }));
