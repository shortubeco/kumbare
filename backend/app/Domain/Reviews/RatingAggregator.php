<?php

namespace App\Domain\Reviews;

use App\Models\Branch;
use Illuminate\Support\Facades\DB;

/** Şubenin ortalama puanını ve değerlendirme sayısını yeniden hesaplar. */
class RatingAggregator
{
    public function refresh(Branch $branch): void
    {
        $row = DB::selectOne(
            "SELECT COUNT(*) AS cnt, ROUND(AVG(score)::numeric, 1) AS avg
             FROM reviews WHERE branch_id = ? AND status = 'published'",
            [$branch->id]
        );

        $branch->forceFill([
            'rating_count' => (int) $row->cnt,
            'rating_avg' => $row->cnt > 0 ? $row->avg : null,
        ])->save();
    }

    /** 1–10 puan dağılımı: [10 => 120, 9 => 80, ...] */
    public function distribution(Branch $branch): array
    {
        $rows = DB::select(
            "SELECT score, COUNT(*) AS cnt FROM reviews
             WHERE branch_id = ? AND status = 'published' GROUP BY score",
            [$branch->id]
        );

        $result = array_fill_keys(range(10, 1), 0);
        foreach ($rows as $r) {
            $result[(int) $r->score] = (int) $r->cnt;
        }

        return $result;
    }
}
