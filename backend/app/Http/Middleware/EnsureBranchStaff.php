<?php

namespace App\Http\Middleware;

use App\Models\PanelUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * İşlem ekranı (/panel/islem): oturum açmış, aktif şube yöneticisi veya çalışan.
 * Ekranın arka plan istekleri JSON bekler; o durumda yönlendirme yerine JSON hata döner.
 * Yetki ayrıntısı (sorgulama / kazandırma / kullandırma) iş kuralı katmanında (StaffGate) kontrol edilir.
 */
class EnsureBranchStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();
        $json = $request->expectsJson();

        if (! $user instanceof PanelUser) {
            return $json
                ? response()->json(['message' => 'Oturumunuz kapanmış. Lütfen yeniden giriş yapın.', 'code' => 'unauthenticated'], 401)
                : redirect()->guest(route('panel.login'));
        }

        if (! $user->isActive()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return $json
                ? response()->json(['message' => 'Hesabınız pasif durumda.', 'code' => 'forbidden'], 403)
                : redirect()->route('panel.login')->withErrors(['email' => 'Hesabınız pasif durumda.']);
        }

        if (! $user->worksAtBranch()) {
            abort(403, 'Bu sayfa yalnızca şube yöneticisi ve çalışanlar içindir.');
        }

        $branch = $user->branch()->with('business')->first();
        if (! $branch || ! $branch->isOperational()) {
            return $json
                ? response()->json(['message' => 'Şube aktif değil; işlem yapılamaz.', 'code' => 'branch_inactive'], 422)
                : redirect()->route('panel.home');
        }

        $user->loadMissing('permissions');
        $request->attributes->set('branch', $branch);

        return $next($request);
    }
}
