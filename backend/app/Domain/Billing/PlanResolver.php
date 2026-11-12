<?php

namespace App\Domain\Billing;

use App\Models\Branch;
use App\Models\Setting;
use App\Models\Subscription;

/** Şubenin geçerli paketi ve Ücretsiz paket limitleri. */
class PlanResolver
{
    public function isPro(Branch $branch): bool
    {
        return Subscription::query()
            ->where('branch_id', $branch->id)
            ->currentlyActive()
            ->exists();
    }

    public function activeSubscription(Branch $branch): ?Subscription
    {
        return Subscription::query()
            ->where('branch_id', $branch->id)
            ->currentlyActive()
            ->orderByDesc('ends_at')
            ->first();
    }

    public function freeMonthlyLimit(): int
    {
        return max(0, Setting::getInt('free_monthly_customer_limit', 100));
    }

    /** Uyarı eşikleri müşteri sayısı olarak: [%80 eşiği, %90 eşiği] */
    public function warningThresholds(): array
    {
        $limit = $this->freeMonthlyLimit();
        $p1 = Setting::getInt('warning_threshold_1', 80);
        $p2 = Setting::getInt('warning_threshold_2', 90);

        return [
            (int) ceil($limit * $p1 / 100),
            (int) ceil($limit * $p2 / 100),
        ];
    }
}
