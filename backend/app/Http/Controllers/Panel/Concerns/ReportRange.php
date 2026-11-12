<?php

namespace App\Http\Controllers\Panel\Concerns;

use App\Domain\Reports\BranchReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/** Rapor ekranlarının ortak tarih aralığı seçimi (hazır aralıklar + özel aralık). */
trait ReportRange
{
    public static function presets(): array
    {
        return [
            '7g' => 'Son 7 gün',
            '30g' => 'Son 30 gün',
            'buay' => 'Bu ay',
            'gecenay' => 'Geçen ay',
            '90g' => 'Son 90 gün',
            'buyil' => 'Bu yıl',
        ];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string, 3: ?string} [başlangıç, bitiş, seçili aralık, hata] */
    protected function range(Request $request): array
    {
        $today = CarbonImmutable::today();
        $preset = (string) $request->query('aralik', '');
        $error = null;

        if ($request->filled('baslangic') || $request->filled('bitis')) {
            $from = $this->rangeDate($request->query('baslangic'));
            $to = $this->rangeDate($request->query('bitis'));
            if (! $from || ! $to) {
                $error = 'Tarihleri gün.ay.yıl biçiminde seçin.';
            } elseif ($from->gt($to)) {
                $error = 'Başlangıç tarihi bitişten sonra olamaz.';
            } elseif ($from->diffInDays($to) + 1 > BranchReport::MAX_DAYS) {
                $error = 'En fazla '.BranchReport::MAX_DAYS.' günlük aralık seçilebilir.';
            } else {
                return [$from, $to->gt($today) ? $today : $to, 'ozel', null];
            }
            $preset = '30g';
        }

        return match ($preset) {
            '7g' => [$today->subDays(6), $today, '7g', $error],
            'buay' => [$today->startOfMonth(), $today, 'buay', $error],
            'gecenay' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()->startOfDay(), 'gecenay', $error],
            '90g' => [$today->subDays(89), $today, '90g', $error],
            'buyil' => [$today->startOfYear(), $today, 'buyil', $error],
            default => [$today->subDays(29), $today, '30g', $error],
        };
    }

    private function rangeDate(mixed $v): ?CarbonImmutable
    {
        return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)
            ? CarbonImmutable::createFromFormat('!Y-m-d', $v) : null;
    }
}
