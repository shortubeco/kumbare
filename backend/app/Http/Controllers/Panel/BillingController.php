<?php

namespace App\Http\Controllers\Panel;

use App\Domain\Billing\PaymentSubmissionService;
use App\Domain\Billing\PlanResolver;
use App\Domain\Counter\MonthlyCounterService;
use App\Domain\DomainException;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\PanelUser;
use App\Models\PaymentNotification;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Sponsorship;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Şube yöneticisi: Paket ve ödeme, Sponsorluk, Havale bildirimi. */
class BillingController extends Controller
{
    public function __construct(private readonly PaymentSubmissionService $payments) {}

    private function branch(Request $request): Branch
    {
        return $request->attributes->get('branch');
    }

    private function manager(): PanelUser
    {
        /** @var PanelUser */
        return Auth::guard('web')->user();
    }

    private function bank(): array
    {
        return [
            'bank_name' => Setting::get('bank_name'),
            'iban' => Setting::get('iban'),
            'account_holder' => Setting::get('account_holder'),
        ];
    }

    public function package(Request $request, PlanResolver $plans, MonthlyCounterService $counter): View
    {
        $branch = $this->branch($request);

        return view('panel.billing.package', [
            'branch' => $branch,
            'status' => $counter->status($branch),
            'subscription' => $plans->activeSubscription($branch),
            'queued' => $branch->subscriptions()->where('status', 'active')->where('starts_at', '>', now())->orderBy('starts_at')->get(),
            'pro' => Plan::query()->where('code', 'pro')->first(),
            'free' => Plan::query()->where('code', 'free')->first(),
            'bank' => $this->bank(),
            'pendingSubscription' => PaymentNotification::query()->where('branch_id', $branch->id)
                ->where('purpose', 'subscription')->where('status', 'pending')->exists(),
            'history' => PaymentNotification::query()->where('branch_id', $branch->id)
                ->orderByDesc('id')->limit(30)->get(),
        ]);
    }

    public function sponsorship(Request $request): View
    {
        $branch = $this->branch($request);

        return view('panel.billing.sponsorship', [
            'branch' => $branch,
            'months' => $this->payments->sponsorshipMonths($branch),
            'price' => $this->payments->expectedAmount('sponsorship', null),
            'bank' => $this->bank(),
            'past' => Sponsorship::query()->where('branch_id', $branch->id)
                ->where('month', '<', now()->startOfMonth()->toDateString())->orderByDesc('month')->limit(12)->get(),
            'history' => PaymentNotification::query()->where('branch_id', $branch->id)
                ->where('purpose', 'sponsorship')->orderByDesc('id')->limit(30)->get(),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $branch = $this->branch($request);
        $purpose = $request->query('amac') === 'sponsorluk' ? 'sponsorship' : 'subscription';
        $cycle = $request->query('donem') === 'yillik' ? 'yearly' : 'monthly';
        $months = $this->payments->sponsorshipMonths($branch);
        $month = (string) $request->query('ay', '');
        if (! isset($months[$month]) || $months[$month] !== 'available') {
            $month = (string) collect($months)->filter(fn ($s) => $s === 'available')->keys()->first();
        }

        return view('panel.billing.create', [
            'branch' => $branch,
            'purpose' => $purpose,
            'cycle' => $cycle,
            'month' => $month,
            'months' => $months,
            'prices' => [
                'monthly' => $this->payments->expectedAmount('subscription', 'monthly'),
                'yearly' => $this->payments->expectedAmount('subscription', 'yearly'),
                'sponsorship' => $this->payments->expectedAmount('sponsorship', null),
            ],
            'bank' => $this->bank(),
            'sender' => $branch->business->legal_name ?: $branch->business->name,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $branch = $this->branch($request);

        $request->merge(['amount' => $this->normalizeMoney($request->input('amount'))]);
        $months = array_keys($this->payments->sponsorshipMonths($branch));

        $data = $request->validate([
            'purpose' => ['required', Rule::in(['subscription', 'sponsorship'])],
            'billing_cycle' => ['required_if:purpose,subscription', 'nullable', Rule::in(['monthly', 'yearly'])],
            'sponsorship_month' => ['required_if:purpose,sponsorship', 'nullable', Rule::in($months)],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'sender_name' => ['required', 'string', 'max:150'],
            'transfer_date' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.now()->subDays(60)->toDateString()],
            'description' => ['nullable', 'string', 'max:1000'],
            'receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:4096'],
        ], [
            'billing_cycle.required_if' => 'Paket dönemini (aylık / yıllık) seçin.',
            'sponsorship_month.required_if' => 'Sponsorluk ayını seçin.',
            'sponsorship_month.in' => 'Seçilen ay için sponsorluk bildirimi yapılamaz.',
            'transfer_date.before_or_equal' => 'Havale tarihi bugünden sonra olamaz.',
            'transfer_date.after_or_equal' => 'Havale tarihi en fazla 60 gün öncesi olabilir.',
            'receipt.mimes' => 'Dekont PDF, JPG, PNG veya WEBP olmalıdır.',
            'receipt.max' => 'Dekont en fazla 4 MB olabilir.',
        ], [
            'amount' => 'tutar', 'sender_name' => 'gönderen adı', 'transfer_date' => 'havale tarihi',
            'description' => 'açıklama', 'receipt' => 'dekont',
        ]);

        try {
            $payment = $this->payments->submit($this->manager(), $branch, $data, $request->file('receipt'));
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route($payment->purpose === 'sponsorship' ? 'panel.sponsorship' : 'panel.package')
            ->with('status', 'Havale bildiriminiz alındı. Kumbare ekibi havaleyi kontrol edip onayladığında burada görünecek.');
    }

    /** Şube yöneticisi yalnızca kendi şubesinin dekontunu açabilir. */
    public function receipt(Request $request, PaymentNotification $payment): StreamedResponse
    {
        abort_unless($payment->branch_id === $this->branch($request)->id, 404);
        abort_unless($payment->receipt_path && Storage::disk('local')->exists($payment->receipt_path), 404);

        return Storage::disk('local')->response($payment->receipt_path);
    }

    private function normalizeMoney(mixed $value): ?string
    {
        $value = str_replace([' ', 'TL', '₺'], '', trim((string) $value));
        if ($value === '') {
            return null;
        }
        if (str_contains($value, ',')) {
            $value = str_replace(['.', ','], ['', '.'], $value);
        }

        return $value;
    }
}
