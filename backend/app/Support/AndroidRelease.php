<?php

namespace App\Support;

/**
 * Siteden indirilen Android uygulaması (APK). Dosyalar public/indir/ altındadır:
 * kumbare.apk ve onu tanımlayan android.json (version_code, version_name, url, notes).
 * Mobil uygulama aynı JSON'u okuyarak "yeni sürüm var" uyarısı gösterir.
 */
class AndroidRelease
{
    /** @return array{version_code:int, version_name:string, url:string, notes:?string, size_mb:?float}|null */
    public static function current(): ?array
    {
        $json = public_path('indir/android.json');
        $apk = public_path('indir/kumbare.apk');
        if (! is_file($json) || ! is_file($apk)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($json), true);
        if (! is_array($data) || ! isset($data['version_code'], $data['url'])) {
            return null;
        }

        return [
            'version_code' => (int) $data['version_code'],
            'version_name' => (string) ($data['version_name'] ?? ''),
            'url' => (string) $data['url'],
            'notes' => $data['notes'] ?? null,
            'size_mb' => round(filesize($apk) / 1048576, 1),
        ];
    }
}
