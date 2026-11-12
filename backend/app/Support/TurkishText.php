<?php

namespace App\Support;

/** Türkçe metin yardımcıları. */
final class TurkishText
{
    /** İlk harfi Türkçe kurallarına göre büyütür: "ilçe" → "İlçe", "ısıtıcı" → "Isıtıcı". */
    public static function ucfirst(string $text): string
    {
        if ($text === '') {
            return $text;
        }

        $first = mb_substr($text, 0, 1, 'UTF-8');
        $upper = match ($first) {
            'i' => 'İ',
            'ı' => 'I',
            default => mb_strtoupper($first, 'UTF-8'),
        };

        return $upper.mb_substr($text, 1, null, 'UTF-8');
    }

    /**
     * Özel isimlere bulunma eki: "İstanbul" → "İstanbul'da", "Muş" → "Muş'ta",
     * "Kadıköy" → "Kadıköy'de", "Iğdır" → "Iğdır'da".
     */
    public static function locative(string $name): string
    {
        $lower = mb_strtolower(strtr($name, ['I' => 'ı', 'İ' => 'i']), 'UTF-8');
        preg_match_all('/[aeıioöuü]/u', $lower, $m);
        $lastVowel = end($m[0]) ?: 'a';
        $vowel = in_array($lastVowel, ['a', 'ı', 'o', 'u'], true) ? 'a' : 'e';

        $lastLetter = mb_substr($lower, -1, 1, 'UTF-8');
        $consonant = in_array($lastLetter, ['ç', 'f', 'h', 'k', 'p', 's', 'ş', 't'], true) ? 't' : 'd';

        return $name."'".$consonant.$vowel;
    }

    /** "İstanbul" → "İstanbul'daki" */
    public static function locativeAdjective(string $name): string
    {
        return self::locative($name).'ki';
    }
}
