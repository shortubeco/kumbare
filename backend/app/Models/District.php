<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class District extends Model
{
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** Büyükşehir olmayan illerde "Merkez" ilçesi il adıyla gösterilir. */
    public function displayName(): string
    {
        return $this->name === 'Merkez' ? $this->city->name.' Merkez' : $this->name;
    }
}
