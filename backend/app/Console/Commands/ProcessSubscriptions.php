<?php

namespace App\Console\Commands;

use App\Domain\Notifications\PanelNotifier;
use App\Models\PanelNotification;
use App\Models\Setting;
use App\Models\Subscription;
use Illuminate\Console\Command;

/**
 * Süresi dolan Profesyonel abonelikleri "expired" yapar (şube otomatik Ücretsiz pakete düşer)
 * ve bitişine N gün kalanlara bir kez uyarı gönderir.
 */
class ProcessSubscriptions extends Command
{
    protected $signature = 'kumbare:process-subscriptions';

    protected $description = 'Abonelik bitişlerini işler ve uyarı bildirimleri üretir';

    public function handle(PanelNotifier $notifier): int
    {
        $now = now();

        $expired = Subscription::query()->with('branch')
            ->where('status', 'active')->where('ends_at', '<=', $now)->get();

        foreach ($expired as $sub) {
            $sub->update(['status' => 'expired']);
            $notifier->notifyBranchManager($sub->branch, 'subscription_expired', [
                'subscription_id' => $sub->id,
                'ended_at' => $sub->ends_at->toIso8601String(),
            ]);
        }

        $days = Setting::getInt('subscription_expiry_warning_days', 7);
        $expiring = Subscription::query()->with('branch')
            ->where('status', 'active')
            ->where('ends_at', '>', $now)
            ->where('ends_at', '<=', $now->copy()->addDays($days))
            ->get();

        $warned = 0;
        foreach ($expiring as $sub) {
            $already = PanelNotification::query()
                ->where('type', 'subscription_expiring')
                ->whereRaw("(data->>'subscription_id')::bigint = ?", [$sub->id])
                ->exists();

            if (! $already) {
                $notifier->notifyBranchManager($sub->branch, 'subscription_expiring', [
                    'subscription_id' => $sub->id,
                    'ends_at' => $sub->ends_at->toIso8601String(),
                ]);
                $warned++;
            }
        }

        $this->info("Süresi dolan: {$expired->count()}, uyarılan: {$warned}");

        return self::SUCCESS;
    }
}
