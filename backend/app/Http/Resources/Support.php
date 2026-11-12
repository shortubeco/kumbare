<?php

namespace App\Http\Resources;

use Illuminate\Support\Facades\Storage;

/** Resource'larda ortak yardımcılar. */
final class Support
{
    public static function fileUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    /** Puan özeti: "8.7 / 10 — 245 değerlendirme" veya "Henüz değerlendirme yok". */
    public static function rating(?string $avg, int $count): array
    {
        return [
            'average' => $count > 0 ? $avg : null,
            'count' => $count,
            'label' => $count > 0
                ? $avg.' / 10 — '.$count.' değerlendirme'
                : 'Henüz değerlendirme yok',
        ];
    }

    /** Ödül oranı etiketi: "%10 ödül kazan" veya "Ödül yok". */
    public static function rewardLabel(string $rate): string
    {
        $value = rtrim(rtrim($rate, '0'), '.');

        return (float) $rate > 0 ? '%'.str_replace('.', ',', $value).' ödül kazan' : 'Ödül yok';
    }

    public static function iso($date): ?string
    {
        return $date?->toIso8601String();
    }
}
