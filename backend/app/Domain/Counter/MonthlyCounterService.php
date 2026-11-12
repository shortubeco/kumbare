<?php

namespace App\Domain\Counter;

use App\Domain\Billing\PlanResolver;
use App\Domain\DomainException;
use App\Domain\Notifications\PanelNotifier;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\MonthlyBranchCounter;
use App\Models\MonthlyBranchCustomer;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Şube bazında aylık tekil müşteri sayacı.
 * Ayrı bir sıfırlama işi yoktur: her kayıt kendi dönemini (ayın 1'i) taşır.
 */
class MonthlyCounterService
{
    public function __construct(
        private readonly PlanResolver $plans,
        private readonly PanelNotifier $notifier,
    ) {}

    public function currentPeriod(): string
    {
        return now()->startOfMonth()->toDateString();
    }

    public function usage(Branch $branch): int
    {
        return (int) MonthlyBranchCounter::query()
            ->where('branch_id', $branch->id)
            ->where('period', $this->currentPeriod())
            ->value('unique_customers');
    }

    public function isCountedThisMonth(Branch $branch, Customer $customer): bool
    {
        return MonthlyBranchCustomer::query()
            ->where('branch_id', $branch->id)
            ->where('period', $this->currentPeriod())
            ->where('customer_id', $customer->id)
            ->exists();
    }

    /** Panel ve önizleme için durum özeti. */
    public function status(Branch $branch, ?Customer $customer = null): array
    {
        $isPro = $this->plans->isPro($branch);
        $limit = $this->plans->freeMonthlyLimit();
        $used = $this->usage($branch);
        $counted = $customer ? $this->isCountedThisMonth($branch, $customer) : null;

        $limitReached = ! $isPro && $used >= $limit;

        return [
            'period' => $this->currentPeriod(),
            'plan' => $isPro ? 'pro' : 'free',
            'unique_customers' => $used,
            'limit' => $isPro ? null : $limit,
            'limit_reached' => $limitReached,
            'customer_counted_this_month' => $counted,
            // Müşteri bu ay zaten sayıldıysa limit dolmuş olsa da ödül kazandırılabilir.
            'can_earn_for_customer' => $customer ? (! $limitReached || $counted) : null,
        ];
    }

    /**
     * Earn işlemi sırasında (açık bir transaction içinde) çağrılır.
     * Sayaç satırını kilitler; müşteri bu ay sayılmamışsa limiti kontrol eder.
     *
     * @return array{counter: MonthlyBranchCounter, already_counted: bool}
     */
    public function lockAndCheck(Branch $branch, Customer $customer): array
    {
        $period = $this->currentPeriod();

        DB::statement(
            'INSERT INTO monthly_branch_counters (branch_id, period, unique_customers, created_at, updated_at)
             VALUES (?, ?, 0, now(), now())
             ON CONFLICT (branch_id, period) DO NOTHING',
            [$branch->id, $period]
        );

        /** @var MonthlyBranchCounter $counter */
        $counter = MonthlyBranchCounter::query()
            ->where('branch_id', $branch->id)
            ->where('period', $period)
            ->lockForUpdate()
            ->firstOrFail();

        $alreadyCounted = MonthlyBranchCustomer::query()
            ->where('branch_id', $branch->id)
            ->where('period', $period)
            ->where('customer_id', $customer->id)
            ->exists();

        if (! $alreadyCounted && ! $this->plans->isPro($branch)
            && $counter->unique_customers >= $this->plans->freeMonthlyLimit()) {
            throw new DomainException(
                'Bu ayın ücretsiz paket müşteri limiti doldu. Bu müşteriye ödül kazandırmak için Profesyonel pakete geçin.',
                'monthly_limit_reached',
                422,
                ['limit' => $this->plans->freeMonthlyLimit(), 'unique_customers' => $counter->unique_customers],
            );
        }

        return ['counter' => $counter, 'already_counted' => $alreadyCounted];
    }

    /** Earn işlemi kaydedildikten sonra, aynı transaction içinde çağrılır. */
    public function registerCustomer(MonthlyBranchCounter $counter, Branch $branch, Customer $customer, WalletTransaction $tx): void
    {
        MonthlyBranchCustomer::query()->create([
            'branch_id' => $branch->id,
            'period' => $counter->period->toDateString(),
            'customer_id' => $customer->id,
            'first_transaction_id' => $tx->id,
        ]);

        $counter->unique_customers++;
        $this->applyThresholds($counter, $branch);
        $counter->save();
    }

    /** %80 / %90 / %100 eşiklerine ilk kez ulaşıldığında şube yöneticisine bildirim. */
    private function applyThresholds(MonthlyBranchCounter $counter, Branch $branch): void
    {
        if ($this->plans->isPro($branch)) {
            return;
        }

        $limit = $this->plans->freeMonthlyLimit();
        [$t1, $t2] = $this->plans->warningThresholds();
        $used = $counter->unique_customers;
        $data = ['unique_customers' => $used, 'limit' => $limit, 'period' => $counter->period->toDateString()];

        if ($used >= $limit && ! $counter->limit_reached_at) {
            $counter->limit_reached_at = now();
            $this->notifier->notifyBranchManager($branch, 'limit_reached', $data);
        } elseif ($used >= $t2 && ! $counter->warned_threshold_2_at) {
            $counter->warned_threshold_2_at = now();
            $this->notifier->notifyBranchManager($branch, 'threshold_2', $data);
        } elseif ($used >= $t1 && ! $counter->warned_threshold_1_at) {
            $counter->warned_threshold_1_at = now();
            $this->notifier->notifyBranchManager($branch, 'threshold_1', $data);
        }
    }
}
