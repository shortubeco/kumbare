<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Notifications\PanelNotifier;
use App\Models\AuditLog;
use App\Models\Sponsorship;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Abonelikler (Profesyonel paket dönemleri) ve sponsorluklar. */
class SubscriptionController extends AdminController
{
    public function index(Request $request): View
    {
        $filter = in_array($request->query('durum'), ['expiring', 'expired', 'cancelled', 'all'], true) ? $request->query('durum') : 'active';
        $now = now();

        $query = Subscription::query()->with('branch.business');
        match ($filter) {
            'active' => $query->where('status', 'active')->where('ends_at', '>', $now)->orderBy('ends_at'),
            'expiring' => $query->where('status', 'active')->where('ends_at', '>', $now)
                ->where('ends_at', '<=', $now->copy()->addDays(30))->orderBy('ends_at'),
            'expired' => $query->where(fn ($w) => $w->where('status', 'expired')
                ->orWhere(fn ($x) => $x->where('status', 'active')->where('ends_at', '<=', $now)))->orderByDesc('ends_at'),
            'cancelled' => $query->where('status', 'cancelled')->orderByDesc('updated_at'),
            default => $query->orderByDesc('id'),
        };

        return view('admin.subscriptions', [
            'subscriptions' => $query->paginate(25)->withQueryString(),
            'filter' => $filter,
        ]);
    }

    /** Hatalı aktivasyonu geri almak için: şube hemen Ücretsiz pakete döner (dönem başka aboneliği yoksa). */
    public function cancel(Request $request, Subscription $subscription, PanelNotifier $notifier): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']], [], ['reason' => 'iptal gerekçesi']);

        if ($subscription->status !== 'active') {
            return back()->with('error', 'Yalnızca aktif abonelik iptal edilebilir.');
        }

        $subscription->update(['status' => 'cancelled']);
        AuditLog::record($this->admin(), 'subscription.cancelled', $subscription, ['reason' => $data['reason']]);
        $notifier->notifyBranchManager($subscription->branch, 'subscription_cancelled', [
            'subscription_id' => $subscription->id, 'reason' => $data['reason'],
        ]);

        return back()->with('status', 'Abonelik iptal edildi.');
    }

    public function sponsorships(Request $request): View
    {
        $month = now()->startOfMonth();
        if (preg_match('/^\d{4}-\d{2}$/', (string) $request->query('ay'))) {
            $month = Carbon::createFromFormat('!Y-m-d', $request->query('ay').'-01')->startOfMonth();
        }

        return view('admin.sponsorships', [
            'month' => $month,
            'sponsorships' => Sponsorship::query()->with('branch.business', 'branch.city', 'branch.district')
                ->where('month', $month->toDateString())->orderBy('status')->orderBy('id')->get(),
            'upcoming' => Sponsorship::query()->where('month', '>', $month->toDateString())
                ->whereIn('status', ['pending', 'active'])
                ->selectRaw('month, COUNT(*) AS cnt')->groupBy('month')->orderBy('month')->get(),
        ]);
    }

    public function cancelSponsorship(Request $request, Sponsorship $sponsorship, PanelNotifier $notifier): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']], [], ['reason' => 'iptal gerekçesi']);

        if (! in_array($sponsorship->status, ['pending', 'active'], true)) {
            return back()->with('error', 'Bu sponsorluk zaten sona ermiş veya iptal edilmiş.');
        }

        $sponsorship->update(['status' => 'cancelled']);
        AuditLog::record($this->admin(), 'sponsorship.cancelled', $sponsorship, ['reason' => $data['reason']]);
        $notifier->notifyBranchManager($sponsorship->branch, 'sponsorship_cancelled', [
            'sponsorship_id' => $sponsorship->id, 'month' => $sponsorship->month->toDateString(), 'reason' => $data['reason'],
        ]);

        return back()->with('status', 'Sponsorluk iptal edildi; şube artık listelerde sponsorlu görünmez.');
    }
}
