<?php

namespace App\Support;

use Carbon\CarbonInterface;

/** Türkçe görüntüleme biçimleri. */
final class Format
{
    private const MONTHS = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];

    public const DAYS = [1 => 'Pazartesi', 2 => 'Salı', 3 => 'Çarşamba', 4 => 'Perşembe', 5 => 'Cuma', 6 => 'Cumartesi', 7 => 'Pazar'];

    /** "1234.5" → "1.234,50 TL" */
    public static function money(string|int|float|null $amount, bool $withCurrency = true): string
    {
        $formatted = number_format((float) $amount, 2, ',', '.');

        return $withCurrency ? $formatted.' TL' : $formatted;
    }

    /** "10.00" → "%10", "12.50" → "%12,5" */
    public static function rate(string|int|float|null $rate): string
    {
        $value = rtrim(rtrim(number_format((float) $rate, 2, ',', ''), '0'), ',');

        return '%'.$value;
    }

    /** Puan iş planındaki gibi noktayla gösterilir: "8.7" */
    public static function score(string|float|null $score): string
    {
        return $score === null ? '–' : number_format((float) $score, 1, '.', '');
    }

    public static function date(?CarbonInterface $date): string
    {
        if (! $date) {
            return '';
        }
        $date = $date->copy()->timezone(config('app.timezone'));

        return $date->day.' '.self::MONTHS[$date->month - 1].' '.$date->year;
    }

    public static function number(int $n): string
    {
        return number_format($n, 0, ',', '.');
    }
}
