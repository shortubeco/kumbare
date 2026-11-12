<?php

namespace App\Console\Commands;

use App\Models\Sponsorship;
use Illuminate\Console\Command;

/**
 * Sponsorluk dönemleri: ayın 1'inde başlar, sonraki ayın 1'inde biter.
 * Listelerdeki sponsorlu bilgisi dönem tarihinden hesaplandığı için
 * bu komut yalnızca durum alanlarını günceller (gecikmesi sorun yaratmaz).
 */
class ProcessSponsorships extends Command
{
    protected $signature = 'kumbare:process-sponsorships';

    protected $description = 'Sponsorluk dönemlerini başlatır ve bitirir';

    public function handle(): int
    {
        $currentMonth = now()->startOfMonth()->toDateString();

        $started = Sponsorship::query()
            ->where('status', 'pending')->where('month', $currentMonth)
            ->update(['status' => 'active', 'updated_at' => now()]);

        $ended = Sponsorship::query()
            ->whereIn('status', ['pending', 'active'])->where('month', '<', $currentMonth)
            ->update(['status' => 'ended', 'updated_at' => now()]);

        $this->info("Başlayan: {$started}, biten: {$ended}");

        return self::SUCCESS;
    }
}
