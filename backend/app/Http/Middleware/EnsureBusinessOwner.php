<?php

namespace App\Http\Middleware;

use App\Models\PanelUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * İşletme paneli (/panel/isletme/...): oturum açmış, aktif işletme yöneticisi ve onaylı (aktif) işletme.
 * Başvurusu bekleyen / reddedilen / askıya alınan işletme bilgi sayfasına (/panel) döner.
 */
class EnsureBusinessOwner
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

        if ($user->role !== PanelUser::ROLE_BUSINESS_OWNER) {
            abort(403, 'Bu sayfa yalnızca işletme yöneticisi içindir.');
        }

        $business = $user->business;
        if (! $business || ! $business->isApproved()) {
            return redirect()->route('panel.home');
        }

        $request->attributes->set('business', $business);

        return $next($request);
    }
}
