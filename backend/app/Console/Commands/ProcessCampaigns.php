<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use Illuminate\Console\Command;

/** Bitiş tarihi geçen kampanyaları "ended" yapar. */
class ProcessCampaigns extends Command
{
    protected $signature = 'kumbare:process-campaigns';

    protected $description = 'Süresi biten kampanyaları kapatır';

    public function handle(): int
    {
        $count = Campaign::query()
            ->where('status', 'active')
            ->where('ends_at', '<=', now())
            ->update(['status' => 'ended', 'updated_at' => now()]);

        $this->info("Kapatılan kampanya: {$count}");

        return self::SUCCESS;
    }
}
