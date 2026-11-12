<?php

use App\Models\Customer;
use App\Models\PanelUser;

return [

    /*
    | İki tür kullanıcı vardır:
    |  - customers   : mobil uygulama müşterileri (Sanctum token)
    |  - panel_users : platform yöneticisi, işletme yöneticisi, şube yöneticisi, çalışan
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'panel_users'),
    ],

    'guards' => [
        // Web panelleri (ileride) oturum tabanlı çalışır.
        'web' => [
            'driver' => 'session',
            'provider' => 'panel_users',
        ],
    ],

    'providers' => [
        'panel_users' => [
            'driver' => 'eloquent',
            'model' => PanelUser::class,
        ],
        'customers' => [
            'driver' => 'eloquent',
            'model' => Customer::class,
        ],
    ],

    'passwords' => [
        'panel_users' => [
            'provider' => 'panel_users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
        'customers' => [
            'provider' => 'customers',
            'table' => 'customer_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
