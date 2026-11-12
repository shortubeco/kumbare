<?php

namespace App\Domain\Loyalty;

use App\Domain\DomainException;
use App\Domain\Support\Money;
use App\Models\ApprovalRequest;
use App\Models\AuditLog;
use App\Models\PanelUser;
use App\Models\Wallet;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Ödül kullandırma isteği. Bakiye ancak müşteri mobil uygulamadan
 * onayladığında düşer (bkz. ApprovalService). Aylık limitten bağımsızdır.
 */
class RedeemService
{
    public function __construct(private readonly StaffGate $gate) {}

    public function request(PanelUser $staff, string $customerNo, string $amount, string $idempotencyKey): ApprovalRequest
    {
        $branch = $this->gate->branchFor($staff, PanelUser::PERM_REDEEM);

        $existing = ApprovalRequest::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            if ($existing->requested_by !== $staff->id || $existing->type !== ApprovalRequest::TYPE_REDEEM) {
                throw new DomainException('Bu işlem anahtarı daha önce kullanılmış.', 'idempotency_conflict', 409);
            }

            return $existing;
        }

        $customer = $this->gate->findCustomer($customerNo);
        $amountCents = Money::toCents($amount);
        if ($amountCents <= 0) {
            throw new DomainException('Kullanılacak tutar sıfırdan büyük olmalıdır.', 'invalid_amount');
        }

        $wallet = Wallet::query()
            ->where('branch_id', $branch->id)
            ->where('customer_id', $customer->id)
            ->first();

        $balanceCents = $wallet ? Money::toCents($wallet->balance) : 0;
        if (! $wallet || $balanceCents < $amountCents) {
            throw new DomainException(
                'Yetersiz bakiye. Bu şubedeki bakiye: '.Money::fromCents($balanceCents).' TL',
                'insufficient_balance',
                422,
                ['balance' => Money::fromCents($balanceCents)],
            );
        }

        try {
            $request = ApprovalRequest::query()->create([
                'type' => ApprovalRequest::TYPE_REDEEM,
                'wallet_id' => $wallet->id,
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'amount' => Money::fromCents($amountCents),
                'status' => ApprovalRequest::STATUS_PENDING,
                'requested_by' => $staff->id,
                'idempotency_key' => $idempotencyKey,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            return ApprovalRequest::query()->where('idempotency_key', $idempotencyKey)->first() ?? throw $e;
        }

        AuditLog::record($staff, 'loyalty.redeem_requested', $request, [
            'customer_no' => $customer->customer_no,
            'amount' => Money::fromCents($amountCents),
        ]);

        return $request;
    }

    /** Personel, şubesine ait isteğin durumunu görür. */
    public function findForStaff(PanelUser $staff, int $requestId): ApprovalRequest
    {
        $branch = $this->gate->branchFor($staff);

        $request = ApprovalRequest::query()
            ->where('id', $requestId)
            ->where('branch_id', $branch->id)
            ->first();

        if (! $request) {
            throw DomainException::notFound('İstek bulunamadı.');
        }

        return $request;
    }

    /** Henüz onaylanmamış isteği geri çeker. */
    public function withdraw(PanelUser $staff, int $requestId): ApprovalRequest
    {
        $request = $this->findForStaff($staff, $requestId);

        $updated = ApprovalRequest::query()
            ->where('id', $request->id)
            ->where('status', ApprovalRequest::STATUS_PENDING)
            ->update(['status' => ApprovalRequest::STATUS_WITHDRAWN, 'responded_at' => now(), 'updated_at' => now()]);

        if ($updated === 0) {
            throw new DomainException('Yalnızca onay bekleyen istekler geri çekilebilir.', 'not_pending');
        }

        AuditLog::record($staff, 'loyalty.request_withdrawn', $request);

        return $request->refresh();
    }
}
