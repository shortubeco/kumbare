<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffPermission extends Model
{
    const UPDATED_AT = null;

    public function panelUser(): BelongsTo
    {
        return $this->belongsTo(PanelUser::class);
    }
}
