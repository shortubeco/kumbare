<?php

namespace App\Http\Middleware;

use App\Models\PanelUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** /yonetim: oturum açmış, aktif platform yöneticisi gerekir. */
class EnsurePlatformAdmin
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

        if ($user->role !== PanelUser::ROLE_PLATFORM_ADMIN) {
            abort(403, 'Bu sayfa yalnızca platform yöneticisi içindir.');
        }

        return $next($request);
    }
}
