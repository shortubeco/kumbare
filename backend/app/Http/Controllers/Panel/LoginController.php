<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PanelUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/** Tüm panel kullanıcıları için ortak giriş (/panel/giris); rolüne göre yönlendirilir. */
class LoginController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if ($user = Auth::guard('web')->user()) {
            return redirect()->to($this->homeFor($user));
        }

        return view('panel.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ], [], ['email' => 'e-posta', 'password' => 'şifre']);

        $email = mb_strtolower($data['email']);
        $user = PanelUser::query()->whereRaw('lower(email) = ?', [$email])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            AuditLog::record(null, 'panel.login_failed', null, ['email' => $email, 'channel' => 'web']);

            return back()->withInput($request->only('email'))->withErrors(['email' => 'E-posta adresi veya şifre hatalı.']);
        }

        if (! $user->isActive()) {
            return back()->withInput($request->only('email'))->withErrors(['email' => 'Hesabınız pasif durumda.']);
        }

        Auth::guard('web')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();
        AuditLog::record($user, 'panel.login', $user, ['channel' => 'web']);

        return redirect()->intended($this->homeFor($user));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('panel.login')->with('status', 'Çıkış yaptınız.');
    }

    /** İşletme/şube/çalışan paneli henüz hazır değil; bilgi sayfası gösterilir. */
    public function home(Request $request): View|RedirectResponse
    {
        $user = Auth::guard('web')->user();
        if (! $user) {
            return redirect()->route('panel.login');
        }
        if ($user->role === PanelUser::ROLE_PLATFORM_ADMIN) {
            return redirect()->route('admin.dashboard');
        }

        $user->loadMissing('business', 'branch.business');

        // İşletme yöneticisi: onaylı işletmede işletme paneline gider.
        if ($user->role === PanelUser::ROLE_BUSINESS_OWNER && $user->isActive() && $user->business?->isApproved()) {
            return redirect()->route('biz.overview');
        }

        // Şube yöneticisi ve çalışan: ana ekran işlem ekranıdır.
        if ($user->worksAtBranch() && $user->isActive() && $user->branch?->isOperational()) {
            return redirect()->route('panel.tx.screen');
        }

        return view('panel.coming-soon', ['user' => $user]);
    }

    public function forgotForm(): View
    {
        return view('panel.forgot');
    }

    public function forgotSend(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email']], [], ['email' => 'e-posta']);

        // Hesap olsun olmasın aynı mesaj gösterilir (e-posta adresi tahmin edilemesin).
        Password::broker('panel_users')->sendResetLink(['email' => mb_strtolower($data['email'])]);

        return back()->with('status', 'Bu adrese kayıtlı bir hesap varsa şifre sıfırlama bağlantısı gönderildi. E-postanızı kontrol edin.');
    }

    private function homeFor(PanelUser $user): string
    {
        return $user->role === PanelUser::ROLE_PLATFORM_ADMIN ? route('admin.dashboard') : route('panel.home');
    }
}
