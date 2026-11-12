<?php

namespace App\Domain\Loyalty;

use App\Models\Branch;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\WalletTransaction;
use Illuminate\Support\Collection;

/**
 * Ödül kazandırma ekranında personelin seçebileceği oran kampanyaları.
 * Personel listeden birini seçer; oranlar toplanmaz.
 */
class EligibleCampaignResolver
{
    /** @return Collection<int, Campaign> */
    public function forCustomer(Branch $branch, Customer $customer): Collection
    {
        $now = now();
        $weekday = (int) $now->dayOfWeekIso;

        $campaigns = Campaign::query()
            ->where('branch_id', $branch->id)
            ->where('type', Campaign::TYPE_RATE_OVERRIDE)
            ->running()
            ->orderByDesc('reward_rate')
            ->orderBy('id')
            ->get();

        if ($campaigns->isEmpty()) {
            return $campaigns;
        }

        $isNewCustomer = ! WalletTransaction::query()
            ->where('branch_id', $branch->id)
            ->where('customer_id', $customer->id)
            ->where('type', WalletTransaction::TYPE_EARN)
            ->exists();

        $isBirthday = $customer->isBirthday($now);

        return $campaigns->filter(function (Campaign $c) use ($weekday, $isNewCustomer, $isBirthday) {
            $days = $c->weekdayList();
            if ($days && ! in_array($weekday, $days, true)) {
                return false;
            }
            if ($c->new_customers_only && ! $isNewCustomer) {
                return false;
            }
            if ($c->birthday_only && ! $isBirthday) {
                return false;
            }

            return true;
        })->values();
    }

    public function findEligible(Branch $branch, Customer $customer, int $campaignId): ?Campaign
    {
        return $this->forCustomer($branch, $customer)->firstWhere('id', $campaignId);
    }
}
