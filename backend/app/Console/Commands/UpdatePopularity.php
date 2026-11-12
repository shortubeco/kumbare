<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** "En popüler" sıralaması: son 30 gündeki ödül kazanma ve kullanma işlemi sayısı. */
class UpdatePopularity extends Command
{
    protected $signature = 'kumbare:update-popularity';

    protected $description = 'Şubelerin popülerlik puanını yeniden hesaplar';

    public function handle(): int
    {
        $affected = DB::update(
            "UPDATE branches b SET popularity_score = COALESCE(t.cnt, 0), updated_at = now()
             FROM branches b2
             LEFT JOIN (
                 SELECT branch_id, COUNT(*) AS cnt FROM wallet_transactions
                 WHERE created_at >= now() - interval '30 days' AND type IN ('earn', 'redeem')
                 GROUP BY branch_id
             ) t ON t.branch_id = b2.id
             WHERE b.id = b2.id AND b.popularity_score IS DISTINCT FROM COALESCE(t.cnt, 0)"
        );

        $this->info("Güncellenen şube: {$affected}");

        return self::SUCCESS;
    }
}
