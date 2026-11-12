<?php

namespace App\Domain\Loyalty;

use App\Domain\DomainException;
use App\Domain\Support\Money;
use App\Models\ApprovalRequest;
use App\Models\AuditLog;
use App\Models\PanelUser;
use App\Models\WalletTransaction;

/**
 * Düzeltme isteği. Yalnızca şube yöneticisi oluşturur; müşteri onaylamadan
 * hiçbir şekilde bakiyeye yansımaz. İşlemler silinmez, ters hareket eklenir.
 */
class CorrectionService
{
    public function __construct(private readonly StaffGate $gate) {}

    public function request(PanelUser $manager, int $transactionId, string $amount, string $reason, string $idempotencyKey): ApprovalRequest
    {
        $branch = $this->gate->requireManager($manager);

        $existing = ApprovalRequest::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            if ($existing->requested_by !== $manager->id || $existing->type !== ApprovalRequest::TYPE_CORRECTION) {
                throw new DomainException('Bu işlem anahtarı daha önce kullanılmış.', 'idempotency_conflict', 409);
            }

            return $existing;
        }

        $tx = WalletTransaction::query()
            ->where('id', $transactionId)
            ->where('branch_id', $branch->id)
            ->first();

        if (! $tx) {
            throw DomainException::notFound('İşlem bulunamadı.');
        }

        if (! in_array($tx->type, [WalletTransaction::TYPE_EARN, WalletTransaction::TYPE_REDEEM], true)) {
            throw new DomainException('Yalnızca ödül kazanma ve kullanma işlemleri düzeltilebilir.', 'not_correctable');
        }

        $amountCents = Money::toCents($amount);
        $originalCents = Money::toCents($tx->amount);
        if ($amountCents === 0) {
            throw new DomainException('Düzeltme tutarı sıfır olamaz.', 'invalid_amount');
        }

        // Kazanım düzeltmesi: o işlemde kazanılandan fazlası geri alınamaz.
        if ($tx->type === WalletTransaction::TYPE_EARN && $amountCents < -$originalCents) {
            throw new DomainException('Düzeltme, işlemde kazanılan tutardan fazla olamaz.', 'invalid_amount');
        }
        // Kullanım düzeltmesi: o işlemde kullanılandan fazlası iade edilemez.
        if ($tx->type === WalletTransaction::TYPE_REDEEM && $amountCents > -$originalCents) {
            throw new DomainException('İade, işlemde kullanılan tutardan fazla olamaz.', 'invalid_amount');
        }

        $hasPending = ApprovalRequest::query()
            ->where('related_transaction_id', $tx->id)
            ->where('status', ApprovalRequest::STATUS_PENDING)
            ->exists();
        if ($hasPending) {
            throw new DomainException('Bu işlem için onay bekleyen bir düzeltme zaten var.', 'correction_pending');
        }

        $request = ApprovalRequest::query()->create([
            'type' => ApprovalRequest::TYPE_CORRECTION,
            'wallet_id' => $tx->wallet_id,
            'branch_id' => $branch->id,
            'customer_id' => $tx->customer_id,
            'amount' => Money::fromCents($amountCents),
            'related_transaction_id' => $tx->id,
            'reason' => $reason,
            'status' => ApprovalRequest::STATUS_PENDING,
            'requested_by' => $manager->id,
            'idempotency_key' => $idempotencyKey,
        ]);

        AuditLog::record($manager, 'loyalty.correction_requested', $request, [
            'transaction_id' => $tx->id,
            'amount' => Money::fromCents($amountCents),
        ]);

        return $request;
    }
}
