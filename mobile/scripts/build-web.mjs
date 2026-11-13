// iPhone web uygulamasını (PWA) canlı API ile derler ve backend/public/uygulama'ya kopyalar.
// Çalıştırma (mobile klasöründe): node scripts/build-web.mjs
// Not: .env.local yerel API'yi gösterir; burada canlı adresler ortam değişkeniyle verilir (öncelikli).
import { execSync } from 'node:child_process';
import { cpSync, existsSync, renameSync, rmSync, writeFileSync } from 'node:fs';

const env = {
  ...process.env,
  EXPO_PUBLIC_API_URL: process.env.EXPO_PUBLIC_API_URL_PROD ?? 'https://kumbareapi.trthabercanli.com/api/v1',
  EXPO_PUBLIC_SITE_URL: process.env.EXPO_PUBLIC_SITE_URL_PROD ?? 'https://kumbare.trthabercanli.com',
};

rmSync('dist-web', { recursive: true, force: true });
execSync('npx expo export --platform web --output-dir dist-web --clear', { stdio: 'inherit', env });

const target = '../backend/public/uygulama';
if (existsSync(target)) rmSync(target, { recursive: true, force: true });
cpSync('dist-web', target, { recursive: true });

// Giriş dosyası app.html: klasörde index.html olursa PHP geliştirme sunucusu alt adresleri yanlış yorumluyor.
// Alt adresleri (ör. /uygulama/kesfet) Laravel AppController karşılar; çıplak /uygulama için DirectoryIndex.
renameSync(`${target}/index.html`, `${target}/app.html`);
writeFileSync(`${target}/.htaccess`, 'DirectoryIndex app.html\nAddType application/manifest+json .webmanifest\n');
console.log(`\nKopyalandı: ${target}`);
