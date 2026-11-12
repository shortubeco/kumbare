<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class MenuItem extends Model
{
    use SoftDeletes;

    protected $casts = [
        'price' => 'decimal:2',
        'is_available' => 'boolean',
    ];
}
