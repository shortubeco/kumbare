<?php

namespace App\Http\Controllers\Admin;

use App\Domain\DomainException;
use App\Http\Controllers\Controller;
use App\Models\PanelUser;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/** Platform yönetimi denetleyicilerinin ortak yardımcıları. */
abstract class AdminController extends Controller
{
    protected function admin(): PanelUser
    {
        /** @var PanelUser */
        return Auth::guard('web')->user();
    }

    /** İş kuralı hatasını formun üstünde gösterir; başarılıysa mesajla geri döner. */
    protected function attempt(Closure $action, string $success, ?string $redirectTo = null): RedirectResponse
    {
        try {
            $action();
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return ($redirectTo ? redirect()->to($redirectTo) : back())->with('status', $success);
    }

    /** Arama metnini LIKE için güvenli hale getirir. */
    protected function like(string $term): string
    {
        return '%'.addcslashes(mb_strtolower(trim($term)), '%_\\').'%';
    }
}
