<?php

namespace App\Http\Controllers\Panel;

use App\Domain\DomainException;
use App\Domain\Loyalty\CorrectionService;
use App\Domain\Loyalty\RedeemService;
use App\Domain\Support\Money;
use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\PanelUser;
use App\Models\WalletTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Şube yöneticisi: düzeltme istekleri. İptal/iade yoktur; hatalı işlem, müşterinin
 * uygulamadan onayladığı ters hareketle (düzeltme kaydı) giderilir. Onaysız bakiye değişmez.
 */
class CorrectionController extends Controller
{
    private function manager(): PanelUser
    {
        /** @var PanelUser */
        return Auth::guard('web')->user();
    }

    public function index(Request $request): View
    {
        $branch = $request->attributes->get('branch');
        $status = in_array($request->query('durum'), ['approved', 'rejected', 'withdrawn', 'all'], true) ? $request->query('durum') : 'pending';

        $query = ApprovalRequest::query()
            ->where('branch_id', $branch->id)
            ->where('type', ApprovalRequest::TYPE_CORRECTION)
            ->with(['customer' => fn ($q) => $q->withTrashed(), 'relatedTransaction', 'requester', 'resultTransaction']);
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return view('panel.corrections.index', [
            'requests' => $query->orderByDesc('id')->paginate(25)->withQueryString(),
            'status' => $status,
            'counts' => DB::table('approval_requests')->where('branch_id', $branch->id)
                ->where('type', ApprovalRequest::TYPE_CORRECTION)
                ->selectRaw('status, COUNT(*) AS cnt')->groupBy('status')->pluck('cnt', 'status'),
        ]);
    }

    public function create(Request $request): View
    {
        $branch = $request->attributes->get('branch');
        $tx = WalletTransaction::query()
            ->where('id', (int) $request->query('islem'))
            ->where('branch_id', $branch->id)
            ->whereIn('type', [WalletTransaction::TYPE_EARN, WalletTransaction::TYPE_REDEEM])
            ->with(['customer' => fn ($q) => $q->withTrashed(), 'wallet', 'campaign'])
            ->firstOrFail();

        return view('panel.corrections.create', [
            'tx' => $tx,
            'pending' => ApprovalRequest::query()->where('related_transaction_id', $tx->id)
                ->where('status', ApprovalRequest::STATUS_PENDING)->first(),
            'previous' => ApprovalRequest::query()->where('related_transaction_id', $tx->id)
                ->where('type', ApprovalRequest::TYPE_CORRECTION)->orderByDesc('id')->get(),
            'key' => (string) Str::uuid(), // çift gönderimde ikinci istek oluşmasın
        ]);
    }

    public function store(Request $request, CorrectionService $corrections): RedirectResponse
    {
        $request->merge(['amount' => $this->normalizeMoney($request->input('amount'))]);

        $data = $request->validate([
            'transaction_id' => ['required', 'integer'],
            'direction' => ['required', 'in:add,subtract'],
            'amount' => ['required', 'regex:'.Money::POSITIVE_REGEX, 'not_in:0,0.00'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'idempotency_key' => ['required', 'uuid'],
        ], [
            'direction.required' => 'Düzeltmenin yönünü seçin (bakiyeye ekle / bakiyeden düş).',
            'amount.regex' => 'Tutarı 125,50 biçiminde yazın.',
        ], ['amount' => 'tutar', 'reason' => 'gerekçe']);

        $signed = ($data['direction'] === 'subtract' ? '-' : '').$data['amount'];

        try {
            $approval = $corrections->request($this->manager(), (int) $data['transaction_id'], $signed, $data['reason'], $data['idempotency_key']);
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $approval->loadMissing('customer');

        return redirect()->route('panel.corrections')
            ->with('status', 'Düzeltme isteği '.$approval->customer->fullName().' adlı müşterinin uygulamasına gönderildi. Müşteri onaylamadan bakiye değişmez.');
    }

    public function withdraw(Request $request, int $approvalRequest, RedeemService $requests): RedirectResponse
    {
        $branch = $request->attributes->get('branch');
        $ok = ApprovalRequest::query()->where('id', $approvalRequest)->where('branch_id', $branch->id)
            ->where('type', ApprovalRequest::TYPE_CORRECTION)->exists();
        abort_unless($ok, 404);

        try {
            $requests->withdraw($this->manager(), $approvalRequest);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Düzeltme isteği geri çekildi.');
    }

    private function normalizeMoney(mixed $value): ?string
    {
        $value = str_replace([' ', 'TL', '₺', '-', '+'], '', trim((string) $value));
        if ($value === '') {
            return null;
        }
        if (str_contains($value, ',')) {
            $value = str_replace(['.', ','], ['', '.'], $value);
        }

        return $value;
    }
}
