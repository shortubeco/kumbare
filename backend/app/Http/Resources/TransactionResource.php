<?php

namespace App\Http\Resources;

use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    private const LABELS = [
        WalletTransaction::TYPE_EARN => 'Kazanıldı',
        WalletTransaction::TYPE_REDEEM => 'Kullanıldı',
        WalletTransaction::TYPE_CORRECTION => 'Düzeltme',
    ];

    public function toArray(Request $request): array
    {
        /** @var WalletTransaction $t */
        $t = $this->resource;

        return [
            'id' => $t->id,
            'type' => $t->type,
            'label' => self::LABELS[$t->type] ?? $t->type,
            'amount' => (string) $t->amount,
            'spend_amount' => $t->spend_amount !== null ? (string) $t->spend_amount : null,
            'reward_rate' => $t->reward_rate !== null ? (string) $t->reward_rate : null,
            'campaign' => $t->campaign ? ['id' => $t->campaign->id, 'title' => $t->campaign->title] : null,
            'balance_after' => (string) $t->balance_after,
            'related_transaction_id' => $t->related_transaction_id,
            'note' => $t->note,
            'created_at' => Support::iso($t->created_at),
        ];
    }
}
