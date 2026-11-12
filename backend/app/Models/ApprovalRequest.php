<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Müşterinin mobil uygulamadan onaylaması gereken istek (ödül kullanımı veya düzeltme). */
class ApprovalRequest extends Model
{
    public const TYPE_REDEEM = 'redeem';
    public const TYPE_CORRECTION = 'correction';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_WITHDRAWN = 'withdrawn';

    protected $casts = [
        'amount' => 'decimal:2',
        'responded_at' => 'datetime',
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

    public function relatedTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class, 'related_transaction_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(PanelUser::class, 'requested_by');
    }

    public function resultTransaction(): HasOne
    {
        return $this->hasOne(WalletTransaction::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
