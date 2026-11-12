<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Billing\PlanResolver;
use App\Domain\Platform\PaymentReviewService;
use App\Models\PaymentNotification;
use App\Models\Plan;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Havale bildirimleri: onaylanınca abonelik veya sponsorluk otomatik oluşur. */
class PaymentController extends AdminController
{
    public function __construct(private readonly PaymentReviewService $service) {}

    public function index(Request $request): View
    {
        $status = in_array($request->query('durum'), ['approved', 'rejected', 'all'], true) ? $request->query('durum') : 'pending';

        $query = PaymentNotification::query()->with(['branch.business']);
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        $status === 'pending' ? $query->orderBy('created_at') : $query->orderByDesc('created_at');

        return view('admin.payments.index', [
            'payments' => $query->paginate(25)->withQueryString(),
            'status' => $status,
            'counts' => DB::table('payment_notifications')->selectRaw('status, COUNT(*) AS cnt')->groupBy('status')->pluck('cnt', 'status'),
        ]);
    }

    public function show(PaymentNotification $payment, PlanResolver $plans): View
    {
        $payment->load(['branch.business', 'submitter', 'reviewer']);
        $pro = Plan::query()->where('code', 'pro')->first();

        $expected = match (true) {
            $payment->purpose === 'sponsorship' => Setting::get('sponsorship_monthly_price'),
            $payment->billing_cycle === 'yearly' => $pro?->yearly_price,
            default => $pro?->monthly_price,
        };

        return view('admin.payments.show', [
            'payment' => $payment,
            'expected' => $expected,
            'activeSubscription' => $plans->activeSubscription($payment->branch),
            'history' => PaymentNotification::query()->where('branch_id', $payment->branch_id)
                ->where('id', '<>', $payment->id)->orderByDesc('id')->limit(10)->get(),
            'hasReceipt' => $payment->receipt_path && Storage::disk('local')->exists($payment->receipt_path),
        ]);
    }

    public function approve(Request $request, PaymentNotification $payment): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);
        $message = $payment->purpose === 'subscription'
            ? 'Havale onaylandı; şubenin Profesyonel paketi aktif edildi.'
            : 'Havale onaylandı; sponsorluk kaydı oluşturuldu.';

        return $this->attempt(fn () => $this->service->approve($this->admin(), $payment, $data['note'] ?? null), $message);
    }

    public function reject(Request $request, PaymentNotification $payment): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:1000']], [], ['note' => 'ret gerekçesi']);

        return $this->attempt(fn () => $this->service->reject($this->admin(), $payment, $data['note']),
            'Havale bildirimi reddedildi; şube yöneticisine bildirildi.');
    }

    /** Dekont dosyaları herkese açık klasörde değil, uygulamanın özel deposunda durur. */
    public function receipt(PaymentNotification $payment): StreamedResponse
    {
        abort_unless($payment->receipt_path && Storage::disk('local')->exists($payment->receipt_path), 404);

        return Storage::disk('local')->response($payment->receipt_path);
    }
}
