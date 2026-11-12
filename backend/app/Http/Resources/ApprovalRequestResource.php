<?php

namespace App\Http\Resources;

use App\Domain\Support\Money;
use App\Models\ApprovalRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Müşterinin Bildirimler ekranındaki onay istekleri. */
class ApprovalRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ApprovalRequest $r */
        $r = $this->resource;
        $balance = Money::toCents((string) $r->wallet->balance);
        $amount = Money::toCents((string) $r->amount);
        $delta = $r->type === ApprovalRequest::TYPE_REDEEM ? -$amount : $amount;
        $branch = $r->branch;

        return [
            'id' => $r->id,
            'type' => $r->type,
            'title' => $r->type === ApprovalRequest::TYPE_REDEEM ? 'Ödül Kullanımı' : 'Düzeltme',
            'status' => $r->status,
            'branch' => [
                'id' => $branch->id,
                'display_name' => $branch->business->name.' – '.$branch->name,
            ],
            'amount' => (string) $r->amount,
            'current_balance' => Money::fromCents($balance),
            'balance_after' => Money::fromCents($balance + $delta),
            'requested_by' => $r->requester?->first_name,
            'reason' => $r->reason,
            'related_transaction' => $r->relatedTransaction ? [
                'id' => $r->relatedTransaction->id,
                'type' => $r->relatedTransaction->type,
                'amount' => (string) $r->relatedTransaction->amount,
                'created_at' => Support::iso($r->relatedTransaction->created_at),
            ] : null,
            'failure_reason' => $r->failure_reason,
            'created_at' => Support::iso($r->created_at),
            'responded_at' => Support::iso($r->responded_at),
        ];
    }
}
