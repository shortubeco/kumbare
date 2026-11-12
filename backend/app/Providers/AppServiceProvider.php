<?php

namespace App\Providers;

use App\Models\Customer;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Giriş, kayıt ve şifre sıfırlama: IP başına dakikada 10 istek.
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        // Müşteri numarası sorgulama: numaralar sıralı olduğu için toplu taramaya karşı
        // personel başına dakikada 20 sorgu.
        RateLimiter::for('customer-lookup', fn (Request $request) => Limit::perMinute(20)
            ->by('lookup:'.($request->user()?->getKey() ?? $request->ip())));

        // Şifre sıfırlama bağlantıları web sitesindeki sayfalara gider.
        ResetPassword::createUrlUsing(function ($user, string $token) {
            $path = $user instanceof Customer ? '/sifre-sifirla' : '/panel/sifre-sifirla';

            return rtrim(config('app.url'), '/').$path.'?token='.$token.'&email='.urlencode($user->getEmailForPasswordReset());
        });
    }
}
