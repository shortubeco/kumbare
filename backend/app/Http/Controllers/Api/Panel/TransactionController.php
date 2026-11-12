<?php

namespace App\Http\Controllers\Api\Panel;

use App\Domain\Counter\MonthlyCounterService;
use App\Domain\Loyalty\CorrectionService;
use App\Domain\Loyalty\EarnService;
use App\Domain\Loyalty\EligibleCampaignResolver;
use App\Domain\Loyalty\RedeemService;
use App\Domain\Loyalty\StaffGate;
use App\Domain\Support\Money;
use App\Http\Controllers\Controller;
use App\Http\Resources\CampaignResource;
use App\Http\Resources\Support;
use App\Http\Resources\TransactionResource;
use App\Models\ApprovalRequest;
use App\Models\AuditLog;
use App\Models\PanelUser;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Şube personelinin işlem ekranı: müşteri numarası → ödül kazandır / kullandır,
 * onay durumunu izleme, düzeltme isteği.
 */
class TransactionController extends Controller
{
    public function __construct(private readonly StaffGate $gate) {}

    /** Müşteri numarası sorgulama: ad-soyad, bu şubedeki bakiye, doğum günü etiketi. */
    public function lookup(Request $request, string $customerNo, MonthlyCounterService $counter): JsonResponse
    {
        /** @var PanelUser $staff */
        $staff = $request->user();
        $branch = $this->gate->branchFor($staff, PanelUser::PERM_LOOKUP, PanelUser::PERM_EARN, PanelUser::PERM_REDEEM);
        $customer = $this->gate->findCustomer($customerNo);

        AuditLog::record($staff, 'customer.lookup', $customer, ['customer_no' => $customer->customer_no]);

        $wallet = Wallet::query()->where('branch_id', $branch->id)->where('customer_id', $customer->id)->first();

        return response()->json(['data' => [
            'customer' => [
                'customer_no' => $customer->customer_no,
                'full_name' => $customer->fullName(),
                // Doğum tarihi personele gösterilmez; yalnızca bugün doğum günü olup olmadığı.
                'is_birthday_today' => $customer->isBirthday(),
            ],
            'wallet' => [
                'balance' => $wallet ? (string) $wallet->balance : '0.00',
                'total_earned' => $wallet ? (string) $wallet->total_earned : '0.00',
                'total_redeemed' => $wallet ? (string) $wallet->total_redeemed : '0.00',
            ],
            'branch_reward_rate' => (string) $branch->reward_rate,
            'monthly_counter' => $counter->status($branch, $customer),
            'pending_requests' => ApprovalRequest::query()
                ->where('branch_id', $branch->id)
                ->where('customer_id', $customer->id)
                ->where('status', ApprovalRequest::STATUS_PENDING)
                ->count(),
        ]]);
    }

    /** Bu müşteri için şu an seçilebilecek oran kampanyaları (doğum günü dahil). */
    public function campaigns(Request $request, string $customerNo, EligibleCampaignResolver $resolver): JsonResponse
    {
        $branch = $this->gate->branchFor($request->user(), PanelUser::PERM_EARN);
        $customer = $this->gate->findCustomer($customerNo);

        return response()->json([
            'default_rate' => (string) $branch->reward_rate,
            'data' => CampaignResource::collection($resolver->forCustomer($branch, $customer))->toArray($request),
        ]);
    }

    /** Müşterinin bu şubedeki işlem geçmişi (düzeltme için işlem seçimi). */
    public function history(Request $request, string $customerNo): AnonymousResourceCollection
    {
        $branch = $this->gate->branchFor($request->user(), PanelUser::PERM_VIEW_CUSTOMERS);
        $customer = $this->gate->findCustomer($customerNo);

        $transactions = WalletTransaction::query()
            ->where('branch_id', $branch->id)
            ->where('customer_id', $customer->id)
            ->with('campaign')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(30);

        return TransactionResource::collection($transactions);
    }

    public function earnPreview(Request $request, EarnService $earn): JsonResponse
    {
        $data = $this->validateEarn($request, false);

        return response()->json(['data' => $earn->preview(
            $request->user(),
            (string) $data['customer_no'],
            (string) $data['spend_amount'],
            isset($data['campaign_id']) ? (int) $data['campaign_id'] : null,
        )]);
    }

    public function earn(Request $request, EarnService $earn): JsonResponse
    {
        $data = $this->validateEarn($request, true);

        $tx = $earn->earn(
            $request->user(),
            (string) $data['customer_no'],
            (string) $data['spend_amount'],
            isset($data['campaign_id']) ? (int) $data['campaign_id'] : null,
            $data['idempotency_key'],
        );

        return response()->json([
            'message' => $tx->amount.' TL ödül kumbaraya eklendi.',
            'data' => (new TransactionResource($tx))->toArray($request),
        ], $tx->wasRecentlyCreated ? 201 : 200);
    }

    public function redeem(Request $request, RedeemService $redeem): JsonResponse
    {
        $data = $request->validate([
            'customer_no' => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'regex:'.Money::POSITIVE_REGEX],
            'idempotency_key' => ['required', 'uuid'],
        ]);

        $approval = $redeem->request($request->user(), (string) $data['customer_no'], $data['amount'], $data['idempotency_key']);

        return response()->json([
            'message' => 'Müşteri onayı bekleniyor. Onay gelmeden indirim uygulamayın.',
            'data' => $this->requestPayload($approval),
        ], $approval->wasRecentlyCreated ? 201 : 200);
    }

    /** İsteğin durumu (personel ekranı birkaç saniyede bir sorgular). */
    public function showRequest(Request $request, int $approvalRequest, RedeemService $redeem): JsonResponse
    {
        return response()->json(['data' => $this->requestPayload($redeem->findForStaff($request->user(), $approvalRequest))]);
    }

    public function withdraw(Request $request, int $approvalRequest, RedeemService $redeem): JsonResponse
    {
        $model = $redeem->withdraw($request->user(), $approvalRequest);

        return response()->json(['message' => 'İstek geri çekildi.', 'data' => $this->requestPayload($model)]);
    }

    public function correction(Request $request, CorrectionService $corrections): JsonResponse
    {
        $data = $request->validate([
            'transaction_id' => ['required', 'integer'],
            'amount' => ['required', 'regex:'.Money::SIGNED_REGEX],
            'reason' => ['required', 'string', 'max:1000'],
            'idempotency_key' => ['required', 'uuid'],
        ]);

        $approval = $corrections->request(
            $request->user(),
            (int) $data['transaction_id'],
            $data['amount'],
            $data['reason'],
            $data['idempotency_key'],
        );

        return response()->json([
            'message' => 'Düzeltme isteği müşteriye gönderildi. Müşteri onaylamadan bakiye değişmez.',
            'data' => $this->requestPayload($approval),
        ], $approval->wasRecentlyCreated ? 201 : 200);
    }

    private function validateEarn(Request $request, bool $withKey): array
    {
        return $request->validate(array_filter([
            'customer_no' => ['required', 'integer', 'min:1'],
            'spend_amount' => ['required', 'regex:'.Money::POSITIVE_REGEX],
            'campaign_id' => ['nullable', 'integer'],
            'idempotency_key' => $withKey ? ['required', 'uuid'] : null,
        ]));
    }

    private function requestPayload(ApprovalRequest $r): array
    {
        $r->loadMissing('customer');

        $statusLabel = match ($r->status) {
            ApprovalRequest::STATUS_PENDING => 'Müşteri onayı bekleniyor',
            ApprovalRequest::STATUS_APPROVED => $r->type === ApprovalRequest::TYPE_REDEEM
                ? 'Onaylandı: '.$r->amount.' TL indirimi uygulayın'
                : 'Onaylandı',
            ApprovalRequest::STATUS_REJECTED => $r->failure_reason ? 'Reddedildi: '.$r->failure_reason : 'Müşteri reddetti',
            ApprovalRequest::STATUS_WITHDRAWN => 'Geri çekildi',
            default => $r->status,
        };

        return [
            'id' => $r->id,
            'type' => $r->type,
            'status' => $r->status,
            'status_label' => $statusLabel,
            'amount' => (string) $r->amount,
            'customer' => [
                'customer_no' => $r->customer->customer_no,
                'full_name' => $r->customer->fullName(),
            ],
            'related_transaction_id' => $r->related_transaction_id,
            'reason' => $r->reason,
            'balance_after' => $r->status === ApprovalRequest::STATUS_APPROVED
                ? (string) $r->resultTransaction?->balance_after
                : null,
            'created_at' => Support::iso($r->created_at),
            'responded_at' => Support::iso($r->responded_at),
        ];
    }
}
