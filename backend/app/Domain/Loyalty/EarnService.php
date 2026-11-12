<?php

namespace App\Domain\Loyalty;

use App\Domain\Counter\MonthlyCounterService;
use App\Domain\DomainException;
use App\Domain\Support\Money;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\PanelUser;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Ödül kazandırma. Müşteri ödemeyi yaptıktan sonra, ödenen tutar üzerinden
 * şubenin (veya personelin seçtiği kampanyanın) o anki oranıyla hesaplanır.
 * Müşteri onayı gerekmez.
 */
class EarnService
{
    public function __construct(
        private readonly StaffGate $gate,
        private readonly EligibleCampaignResolver $campaigns,
        private readonly MonthlyCounterService $counter,
    ) {}

    /** Özet ekranı için hesaplama; hiçbir şey yazmaz. */
    public function preview(PanelUser $staff, string $customerNo, string $spendAmount, ?int $campaignId): array
    {
        $branch = $this->gate->branchFor($staff, PanelUser::PERM_EARN);
        $customer = $this->gate->findCustomer($customerNo);
        [$rate, $campaign] = $this->resolveRate($branch, $customer, $campaignId);

        $spendCents = Money::toCents($spendAmount);
        $rewardCents = Money::percentOf($spendCents, $rate);

        $wallet = Wallet::query()->where('branch_id', $branch->id)->where('customer_id', $customer->id)->first();
        $balanceCents = $wallet ? Money::toCents($wallet->balance) : 0;

        $status = $this->counter->status($branch, $customer);

        return [
            'customer' => [
                'customer_no' => $customer->customer_no,
                'full_name' => $customer->fullName(),
            ],
            'spend_amount' => Money::fromCents($spendCents),
            'reward_rate' => $rate,
            'campaign' => $campaign ? ['id' => $campaign->id, 'title' => $campaign->title] : null,
            'reward_amount' => Money::fromCents($rewardCents),
            'current_balance' => Money::fromCents($balanceCents),
            'new_balance' => Money::fromCents($balanceCents + $rewardCents),
            'can_earn' => (bool) $status['can_earn_for_customer'],
            'monthly_counter' => $status,
        ];
    }

    public function earn(PanelUser $staff, string $customerNo, string $spendAmount, ?int $campaignId, string $idempotencyKey): WalletTransaction
    {
        $branch = $this->gate->branchFor($staff, PanelUser::PERM_EARN);

        $existing = WalletTransaction::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            if ($existing->performed_by !== $staff->id || $existing->type !== WalletTransaction::TYPE_EARN) {
                throw new DomainException('Bu işlem anahtarı daha önce kullanılmış.', 'idempotency_conflict', 409);
            }

            return $existing;
        }

        $customer = $this->gate->findCustomer($customerNo);
        $spendCents = Money::toCents($spendAmount);
        if ($spendCents <= 0) {
            throw new DomainException('Harcama tutarı sıfırdan büyük olmalıdır.', 'invalid_amount');
        }

        try {
            $tx = $this->persist($staff, $branch, $customer, $spendCents, $campaignId, $idempotencyKey);
        } catch (UniqueConstraintViolationException $e) {
            // Aynı anahtarla eş zamanlı ikinci gönderim: ilk işlemi döndür.
            $tx = WalletTransaction::query()->where('idempotency_key', $idempotencyKey)->first() ?? throw $e;
        }

        return $tx->load('campaign');
    }

    private function persist(PanelUser $staff, Branch $branch, Customer $customer, int $spendCents, ?int $campaignId, string $idempotencyKey): WalletTransaction
    {
        return DB::transaction(function () use ($staff, $branch, $customer, $spendCents, $campaignId, $idempotencyKey) {
            ['counter' => $counter, 'already_counted' => $alreadyCounted] = $this->counter->lockAndCheck($branch, $customer);

            // Kampanya uygunluğu işlem anında tekrar doğrulanır.
            [$rate, $campaign] = $this->resolveRate($branch, $customer, $campaignId);
            $rewardCents = Money::percentOf($spendCents, $rate);
            if ($rewardCents <= 0) {
                throw new DomainException('Bu harcama için kazanılacak ödül 0,00 TL; işlem yapılmadı.', 'zero_reward');
            }

            $wallet = $this->lockWallet($branch, $customer);
            $newBalance = Money::toCents($wallet->balance) + $rewardCents;

            $wallet->balance = Money::fromCents($newBalance);
            $wallet->total_earned = Money::fromCents(Money::toCents($wallet->total_earned) + $rewardCents);
            $wallet->save();

            $tx = WalletTransaction::query()->create([
                'wallet_id' => $wallet->id,
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'type' => WalletTransaction::TYPE_EARN,
                'amount' => Money::fromCents($rewardCents),
                'spend_amount' => Money::fromCents($spendCents),
                'reward_rate' => $rate,
                'campaign_id' => $campaign?->id,
                'balance_after' => Money::fromCents($newBalance),
                'idempotency_key' => $idempotencyKey,
                'performed_by' => $staff->id,
            ]);

            if (! $alreadyCounted) {
                $this->counter->registerCustomer($counter, $branch, $customer, $tx);
            }

            AuditLog::record($staff, 'loyalty.earn', $tx, [
                'customer_no' => $customer->customer_no,
                'spend' => Money::fromCents($spendCents),
                'rate' => $rate,
            ]);

            return $tx;
        });
    }

    /**
     * Uygulanacak oran: seçilen kampanya (uygunsa) veya şubenin oranı.
     *
     * @return array{0: string, 1: ?Campaign}
     */
    private function resolveRate(Branch $branch, Customer $customer, ?int $campaignId): array
    {
        if ($campaignId) {
            $campaign = $this->campaigns->findEligible($branch, $customer, $campaignId);
            if (! $campaign) {
                throw new DomainException(
                    'Seçilen kampanya bu müşteri için şu anda geçerli değil.',
                    'campaign_not_eligible'
                );
            }

            return [(string) $campaign->reward_rate, $campaign];
        }

        $rate = (string) $branch->reward_rate;
        if (Money::rateToBasis($rate) <= 0) {
            throw new DomainException(
                'Şubenin ödül oranı %0; ödül kazandırılamaz.',
                'reward_disabled'
            );
        }

        return [$rate, null];
    }

    /** Kumbara yoksa oluşturur ve satırı kilitler. */
    private function lockWallet(Branch $branch, Customer $customer): Wallet
    {
        DB::statement(
            'INSERT INTO wallets (customer_id, branch_id, balance, total_earned, total_redeemed, created_at, updated_at)
             VALUES (?, ?, 0, 0, 0, now(), now())
             ON CONFLICT (customer_id, branch_id) DO NOTHING',
            [$customer->id, $branch->id]
        );

        return Wallet::query()
            ->where('customer_id', $customer->id)
            ->where('branch_id', $branch->id)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
