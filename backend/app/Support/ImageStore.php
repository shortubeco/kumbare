<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Yüklenen görselleri herkese açık diske (public/uploads) kaydeder.
 * GD varsa en uzun kenarı $max pikseli geçmeyecek şekilde küçültür ve WebP'ye çevirir
 * (meta veriler — ör. konum — da böylece silinir); GD yoksa dosyayı olduğu gibi kaydeder.
 */
final class ImageStore
{
    public static function store(UploadedFile $file, string $dir, int $max = 1600): string
    {
        $name = Str::lower(Str::random(32));
        $disk = Storage::disk('public');

        $converted = self::toWebp($file->getRealPath(), $max);
        if ($converted !== null) {
            $path = trim($dir, '/').'/'.$name.'.webp';
            $disk->put($path, $converted);

            return $path;
        }

        $ext = strtolower($file->extension() ?: $file->getClientOriginalExtension() ?: 'jpg');

        return $file->storeAs(trim($dir, '/'), $name.'.'.$ext, 'public');
    }

    public static function delete(?string $path): void
    {
        if ($path && ! str_starts_with($path, 'http')) {
            Storage::disk('public')->delete($path);
        }
    }

    private static function toWebp(string $source, int $max): ?string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            return null;
        }

        $data = @file_get_contents($source);
        $img = $data !== false ? @imagecreatefromstring($data) : false;
        if (! $img) {
            return null;
        }

        // Telefon fotoğraflarının yönü (EXIF) düzeltilir
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($source);
            $angle = match ((int) ($exif['Orientation'] ?? 1)) { 3 => 180, 6 => -90, 8 => 90, default => 0 };
            if ($angle !== 0) {
                $rotated = imagerotate($img, $angle, 0);
                if ($rotated) {
                    imagedestroy($img);
                    $img = $rotated;
                }
            }
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $scale = min(1, $max / max($w, $h));
        if ($scale < 1) {
            $nw = (int) round($w * $scale);
            $nh = (int) round($h * $scale);
            $resized = imagecreatetruecolor($nw, $nh);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($img);
            $img = $resized;
        }

        ob_start();
        imagewebp($img, null, 82);
        $out = ob_get_clean();
        imagedestroy($img);

        return $out !== false && $out !== '' ? $out : null;
    }
}
