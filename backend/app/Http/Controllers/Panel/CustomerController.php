<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\Customer;
use App\Models\PanelUser;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Şube: bu şubede işlem yapmış müşteriler ve işlem geçmişleri.
 * Şube yöneticisi ve "müşterileri görüntüleme" yetkili çalışan görür.
 * Telefon, e-posta ve doğum tarihi gösterilmez.
 */
class CustomerController extends Controller
{
    private const SORTS = [
        'son' => 'last_tx DESC NULLS LAST',
        'bakiye' => 'w.balance DESC',
        'kazanilan' => 'w.total_earned DESC',
        'islem' => 'tx_count DESC',
        'no' => 'c.customer_no ASC',
    ];

    private function authorizeView(): PanelUser
    {
        /** @var PanelUser $user */
        $user = Auth::guard('web')->user();
        abort_unless($user->hasPermission(PanelUser::PERM_VIEW_CUSTOMERS), 403, 'Müşterileri görüntüleme yetkiniz yok.');

        return $user;
    }

    public function index(Request $request): View
    {
        $this->authorizeView();
        $branch = $request->attributes->get('branch');
        $q = trim((string) $request->query('q', ''));
        $sort = array_key_exists($request->query('siralama'), self::SORTS) ? $request->query('siralama') : 'son';

        $query = DB::table('wallets AS w')
            ->join('customers AS c', 'c.id', '=', 'w.customer_id')
            ->where('w.branch_id', $branch->id)
            ->select([
                'c.customer_no', 'c.first_name', 'c.last_name', 'c.status', 'c.deleted_at',
                'w.balance', 'w.total_earned', 'w.total_redeemed',
                DB::raw('(SELECT COUNT(*) FROM wallet_transactions t WHERE t.wallet_id = w.id) AS tx_count'),
                DB::raw('(SELECT MAX(created_at) FROM wallet_transactions t WHERE t.wallet_id = w.id) AS last_tx'),
            ]);

        if ($q !== '') {
            if (ctype_digit($q)) {
                $query->where('c.customer_no', (int) $q);
            } else {
                $like = '%'.addcslashes(mb_strtolower($q), '%_\\').'%';
                $query->whereRaw("lower(c.first_name || ' ' || c.last_name) LIKE ?", [$like]);
            }
        }

        $summary = DB::selectOne('SELECT COUNT(*) AS customers, COALESCE(SUM(balance), 0) AS balance FROM wallets WHERE branch_id = ?', [$branch->id]);

        return view('panel.customers.index', [
            'customers' => $query->orderByRaw(self::SORTS[$sort])->orderBy('c.customer_no')->paginate(25)->withQueryString(),
            'q' => $q,
            'sort' => $sort,
            'summary' => $summary,
        ]);
    }

    public function show(Request $request, string $customerNo): View
    {
        $user = $this->authorizeView();
        $branch = $request->attributes->get('branch');

        $customer = Customer::withTrashed()->where('customer_no', (int) $customerNo)->firstOrFail();
        $wallet = Wallet::query()->where('branch_id', $branch->id)->where('customer_id', $customer->id)->first();
        abort_unless($wallet, 404, 'Bu müşterinin şubenizde işlemi yok.');

        $transactions = WalletTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->with(['campaign', 'performer'])
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(30);

        // Her işlem için düzeltme istekleri (bekleyen / sonuçlanan)
        $corrections = ApprovalRequest::query()
            ->where('type', ApprovalRequest::TYPE_CORRECTION)
            ->whereIn('related_transaction_id', $transactions->pluck('id'))
            ->orderByDesc('id')
            ->get()
            ->groupBy('related_transaction_id');

        $pending = ApprovalRequest::query()
            ->where('wallet_id', $wallet->id)
            ->where('status', ApprovalRequest::STATUS_PENDING)
            ->with('relatedTransaction')
            ->orderByDesc('id')->get();

        return view('panel.customers.show', [
            'customer' => $customer,
            'wallet' => $wallet,
            'transactions' => $transactions,
            'corrections' => $corrections,
            'pending' => $pending,
            'canCorrect' => $user->isBranchManager(),
        ]);
    }
}
