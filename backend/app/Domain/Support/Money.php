<?php

namespace App\Domain\Support;

use InvalidArgumentException;

/**
 * Para hesapları kuruş cinsinden tam sayılarla yapılır; float kullanılmaz.
 * Veritabanı ve API'de tutarlar "1234.50" biçiminde metin olarak taşınır.
 */
final class Money
{
    /** "1234.5", "1234,50", 1234 → 123450 (kuruş) */
    public static function toCents(string|int $value): int
    {
        if (is_int($value)) {
            return $value * 100;
        }

        $value = str_replace(',', '.', trim($value));
        if (! preg_match('/^(-)?(\d+)(?:\.(\d{1,2}))?$/', $value, $m)) {
            throw new InvalidArgumentException("Geçersiz tutar: {$value}");
        }

        $cents = ((int) $m[2]) * 100 + (int) str_pad($m[3] ?? '0', 2, '0');

        return $m[1] === '-' ? -$cents : $cents;
    }

    /** 123450 → "1234.50" */
    public static function fromCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $abs = abs($cents);

        return $sign.intdiv($abs, 100).'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Oran ("15.00") → yüzde birimin yüzde biri (1500). */
    public static function rateToBasis(string|int $rate): int
    {
        return self::toCents((string) $rate);
    }

    /**
     * Tutarın oran kadarını hesaplar, kuruşa normal yuvarlama ile.
     * Örn: 123456 kuruş × %7 → 8641.92 → 8642 kuruş (86,42 TL)
     */
    public static function percentOf(int $cents, string|int $rate): int
    {
        if ($cents < 0) {
            throw new InvalidArgumentException('Negatif tutarın oranı hesaplanamaz.');
        }

        return intdiv($cents * self::rateToBasis($rate) + 5000, 10000);
    }

    /** API girdisi doğrulama kuralı: pozitif, en çok 2 ondalık. */
    public const POSITIVE_REGEX = '/^\d{1,10}([.,]\d{1,2})?$/';

    /** İşaretli tutar doğrulama kuralı. */
    public const SIGNED_REGEX = '/^-?\d{1,10}([.,]\d{1,2})?$/';
}
