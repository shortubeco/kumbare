<?php

namespace App\Http\Middleware;

use App\Models\PanelUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Şube paneli (/panel/...): oturum açmış, aktif şube yöneticisi gerekir.
 * Şube veya işletme kapalıysa (askıya alma, pasif şube) panel bilgi sayfasına döner.
 */
class EnsureBranchManager
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if (! $user instanceof PanelUser) {
            return redirect()->guest(route('panel.login'));
        }

        if (! $user->isActive()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return redirect()->route('panel.login')->withErrors(['email' => 'Hesabınız pasif durumda.']);
        }

        if (! $user->isBranchManager()) {
            abort(403, 'Bu sayfa yalnızca şube yöneticisi içindir.');
        }

        $branch = $user->branch()->with('business')->first();
        if (! $branch || ! $branch->isOperational()) {
            return redirect()->route('panel.home');
        }

        $request->attributes->set('branch', $branch);

        return $next($request);
    }
}
