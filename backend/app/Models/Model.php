<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;

/**
 * Tüm modellerin temel sınıfı.
 * TIMESTAMPTZ kolonlarına saat dilimi bilgisiyle yazılır.
 */
abstract class Model extends EloquentModel
{
    protected $dateFormat = 'Y-m-d H:i:sP';

    protected $guarded = ['id'];
}
