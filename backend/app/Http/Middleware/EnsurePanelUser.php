<?php

namespace App\Http\Middleware;

use App\Models\PanelUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Panel API: token'ın sahibi aktif bir panel kullanıcısı olmalı. */
class EnsurePanelUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof PanelUser || ! $user->isActive()) {
            return response()->json(['message' => 'Bu işlem için panel girişi gerekiyor.', 'code' => 'forbidden'], 403);
        }

        $user->loadMissing('permissions');

        return $next($request);
    }
}
