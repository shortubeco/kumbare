<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Mobil API: token'ın sahibi aktif bir müşteri olmalı. */
class EnsureCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof Customer || $user->status !== 'active' || $user->trashed()) {
            return response()->json(['message' => 'Bu işlem için müşteri girişi gerekiyor.', 'code' => 'forbidden'], 403);
        }

        return $next($request);
    }
}
