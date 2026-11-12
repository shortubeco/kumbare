<?php

namespace App\Models;

class PanelNotification extends Model
{
    const UPDATED_AT = null;

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];
}
