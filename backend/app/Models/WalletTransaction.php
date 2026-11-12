<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Değiştirilemez işlem defteri. Yalnızca INSERT yapılır;
 * veritabanındaki trigger UPDATE ve DELETE'i engeller.
 */
class WalletTransaction extends Model
{
    public const TYPE_EARN = 'earn';
    public const TYPE_REDEEM = 'redeem';
    public const TYPE_CORRECTION = 'correction';

    const UPDATED_AT = null;

    protected $casts = [
        'amount' => 'decimal:2',
        'spend_amount' => 'decimal:2',
        'reward_rate' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function relatedTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_transaction_id');
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(PanelUser::class, 'performed_by');
    }
}
