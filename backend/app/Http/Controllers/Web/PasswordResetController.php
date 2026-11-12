<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/**
 * Şifre sıfırlama e-postalarındaki bağlantıların açtığı sayfa.
 * /sifre-sifirla → müşteriler, /panel/sifre-sifirla → panel kullanıcıları.
 */
class PasswordResetController extends Controller
{
    public function show(Request $request): View
    {
        return view('site.password-reset', [
            'token' => (string) $request->query('token'),
            'email' => (string) $request->query('email'),
            'isPanel' => $request->routeIs('web.panel.password.*'),
            'done' => session('password_reset_done', false),
            'seo' => ['title' => 'Yeni şifre belirle | Kumbare', 'description' => '', 'canonical' => url()->current(), 'robots' => 'noindex,nofollow'],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $isPanel = $request->routeIs('web.panel.password.*');

        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min($isPanel ? 10 : 8)],
        ]);

        $status = Password::broker($isPanel ? 'panel_users' : 'customers')->reset(
            [
                'email' => mb_strtolower($data['email']),
                'token' => $data['token'],
                'password' => $data['password'],
                'password_confirmation' => $request->input('password_confirmation'),
            ],
            function (CanResetPassword $user, string $password) {
                $user->password = $password;
                $user->save();
                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))
                ->withErrors(['token' => 'Bu şifre sıfırlama bağlantısının süresi dolmuş ya da daha önce kullanılmış. Uygulamadan yeni bir bağlantı isteyin.']);
        }

        return redirect()->to(url()->current())->with('password_reset_done', true);
    }
}
