<?php

namespace App\Domain\Loyalty;

use App\Domain\DomainException;
use App\Domain\Notifications\PanelNotifier;
use App\Domain\Support\Money;
use App\Models\ApprovalRequest;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Müşterinin mobil uygulamadan ödül kullanım ve düzeltme isteklerini
 * onaylaması veya reddetmesi. Bakiye yalnızca burada, onayla değişir.
 */
class ApprovalService
{
    public function __construct(private readonly PanelNotifier $notifier) {}

    /**
     * @return array{request: ApprovalRequest, approved: bool, message: string}
     */
    public function approve(Customer $customer, int $requestId): array
    {
        $result = DB::transaction(function () use ($customer, $requestId) {
            $request = $this->lockPending($customer, $requestId);

            /** @var Wallet $wallet */
            $wallet = Wallet::query()->whereKey($request->wallet_id)->lockForUpdate()->firstOrFail();

            $balance = Money::toCents($wallet->balance);
            $amount = Money::toCents($request->amount);
            $earned = Money::toCents($wallet->total_earned);
            $redeemed = Money::toCents($wallet->total_redeemed);

            if ($request->type === ApprovalRequest::TYPE_REDEEM) {
                $delta = -$amount;
                $redeemed += $amount;
            } else {
                $delta = $amount;
                $related = WalletTransaction::query()->findOrFail($request->related_transaction_id);
                if ($related->type === WalletTransaction::TYPE_EARN) {
                    $earned += $amount;
                } else {
                    $redeemed -= $amount;
                }
            }

            $newBalance = $balance + $delta;

            if ($newBalance < 0 || $earned < 0 || $redeemed < 0) {
                $request->status = ApprovalRequest::STATUS_REJECTED;
                $request->failure_reason = 'Yetersiz bakiye';
                $request->responded_at = now();
                $request->save();

                return ['request' => $request, 'approved' => false,
                    'message' => 'Bakiyeniz yetersiz olduğu için işlem gerçekleştirilemedi.'];
            }

            $wallet->balance = Money::fromCents($newBalance);
            $wallet->total_earned = Money::fromCents($earned);
            $wallet->total_redeemed = Money::fromCents($redeemed);
            $wallet->save();

            WalletTransaction::query()->create([
                'wallet_id' => $wallet->id,
                'branch_id' => $request->branch_id,
                'customer_id' => $customer->id,
                'type' => $request->type === ApprovalRequest::TYPE_REDEEM
                    ? WalletTransaction::TYPE_REDEEM
                    : WalletTransaction::TYPE_CORRECTION,
                'amount' => Money::fromCents($delta),
                'balance_after' => Money::fromCents($newBalance),
                'related_transaction_id' => $request->related_transaction_id,
                'approval_request_id' => $request->id,
                'performed_by' => $request->requested_by,
                'note' => $request->reason,
            ]);

            $request->status = ApprovalRequest::STATUS_APPROVED;
            $request->responded_at = now();
            $request->save();

            AuditLog::record($customer, 'loyalty.request_approved', $request);

            return ['request' => $request, 'approved' => true, 'message' => 'İşlem onaylandı.'];
        });

        $this->notifyRequester($result['request'], $result['approved'] ? 'approval_approved' : 'approval_rejected');

        return $result;
    }

    public function reject(Customer $customer, int $requestId): ApprovalRequest
    {
        $request = DB::transaction(function () use ($customer, $requestId) {
            $request = $this->lockPending($customer, $requestId);
            $request->status = ApprovalRequest::STATUS_REJECTED;
            $request->responded_at = now();
            $request->save();

            AuditLog::record($customer, 'loyalty.request_rejected', $request);

            return $request;
        });

        $this->notifyRequester($request, 'approval_rejected');

        return $request;
    }

    private function lockPending(Customer $customer, int $requestId): ApprovalRequest
    {
        $request = ApprovalRequest::query()
            ->where('id', $requestId)
            ->where('customer_id', $customer->id)
            ->lockForUpdate()
            ->first();

        if (! $request) {
            throw DomainException::notFound('İstek bulunamadı.');
        }

        if (! $request->isPending()) {
            throw new DomainException('Bu istek artık onay beklemiyor.', 'not_pending', 409, ['status' => $request->status]);
        }

        return $request;
    }

    private function notifyRequester(ApprovalRequest $request, string $type): void
    {
        $requester = $request->requester;
        if ($requester) {
            $this->notifier->notify($requester, $type, [
                'approval_request_id' => $request->id,
                'request_type' => $request->type,
                'amount' => (string) $request->amount,
            ]);
        }
    }
}
