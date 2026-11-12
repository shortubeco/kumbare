<?php

namespace App\Support;

/**
 * Fotoğrafı olmayan restoranlar için "tabela": mutfağa göre renklenen,
 * adı büyük harflerle taşıyan bir yüzey.
 */
final class Tabela
{
    private const TONES = ['murekkep', 'kumbara', 'cini', 'nar', 'zeytin', 'kum'];

    public static function tone(?int $categoryId): string
    {
        return self::TONES[($categoryId ?? 0) % count(self::TONES)];
    }

    /** "Közbaşı Kebap" → "KK", "Pidemiz" → "Pİ" */
    public static function initials(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name)) ?: [];
        $letters = count($words) >= 2
            ? mb_substr($words[0], 0, 1).mb_substr($words[1], 0, 1)
            : mb_substr($words[0] ?? '', 0, 2);

        return self::upper($letters);
    }

    public static function upper(string $text): string
    {
        return mb_strtoupper(strtr($text, ['i' => 'İ', 'ı' => 'I']), 'UTF-8');
    }
}
