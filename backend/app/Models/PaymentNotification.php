<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Havale bildirimi (abonelik veya sponsorluk). */
class PaymentNotification extends Model
{
    protected $casts = [
        'amount' => 'decimal:2',
        'transfer_date' => 'date',
        'sponsorship_month' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(PanelUser::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(PanelUser::class, 'reviewed_by');
    }
}
