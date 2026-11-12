<?php

namespace App\Http\Controllers\Panel;

use App\Domain\Billing\PlanResolver;
use App\Domain\Counter\MonthlyCounterService;
use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Şube yöneticisi: Genel bakış (yalnızca kendi şubesinin verileri). */
class BranchDashboardController extends Controller
{
    public function __invoke(Request $request, MonthlyCounterService $counter, PlanResolver $plans): View
    {
        $branch = $request->attributes->get('branch');
        $now = CarbonImmutable::now();
        $monthStart = $now->startOfMonth();

        $wallets = DB::selectOne('SELECT COUNT(*) AS customers,
                COALESCE(SUM(balance), 0) AS balance,
                COALESCE(SUM(total_earned), 0) AS earned,
                COALESCE(SUM(total_redeemed), 0) AS redeemed
            FROM wallets WHERE branch_id = ?', [$branch->id]);

        $tx = DB::selectOne("SELECT
                COUNT(*) FILTER (WHERE created_at >= ?) AS today,
                COUNT(*) FILTER (WHERE created_at >= ?) AS week,
                COUNT(*) FILTER (WHERE created_at >= ?) AS month,
                COALESCE(SUM(amount) FILTER (WHERE type = 'earn' AND created_at >= ?), 0) AS month_earned,
                COALESCE(-SUM(amount) FILTER (WHERE type = 'redeem' AND created_at >= ?), 0) AS month_redeemed
            FROM wallet_transactions WHERE branch_id = ? AND created_at >= ?",
            [$now->startOfDay(), $now->subDays(6)->startOfDay(), $monthStart, $monthStart, $monthStart, $branch->id, $now->subDays(31)]);

        // Bu ay ödül kazanan müşteriler (aylık sayaçla aynı ölçüt): ilk kez kazanan (yeni) / daha önce de kazanmış (tekrar gelen)
        $returning = DB::selectOne("SELECT
                COUNT(*) FILTER (WHERE first_tx < ?) AS returning,
                COUNT(*) FILTER (WHERE first_tx >= ?) AS new
            FROM (SELECT customer_id, MIN(created_at) AS first_tx FROM wallet_transactions
                  WHERE branch_id = ? AND type = 'earn' GROUP BY customer_id
                  HAVING MAX(created_at) >= ?) x", [$monthStart, $monthStart, $branch->id, $monthStart]);

        $topCustomers = DB::select("SELECT c.customer_no, c.first_name, c.last_name, COUNT(t.id) AS cnt,
                COALESCE(SUM(t.amount) FILTER (WHERE t.type = 'earn'), 0) AS earned, w.balance
            FROM wallet_transactions t
            JOIN customers c ON c.id = t.customer_id
            JOIN wallets w ON w.id = t.wallet_id
            WHERE t.branch_id = ? AND t.created_at >= ?
            GROUP BY c.customer_no, c.first_name, c.last_name, w.balance
            ORDER BY cnt DESC, earned DESC LIMIT 10", [$branch->id, $now->subDays(30)]);

        $daily = DB::select("SELECT d::date AS day, COUNT(t.id) AS cnt
            FROM generate_series(?::date, ?::date, interval '1 day') d
            LEFT JOIN wallet_transactions t ON t.branch_id = ? AND t.created_at >= d AND t.created_at < d + interval '1 day'
            GROUP BY d ORDER BY d", [$now->subDays(29)->toDateString(), $now->toDateString(), $branch->id]);

        $monthly = DB::select("SELECT m::date AS month,
                COALESCE((SELECT SUM(amount) FROM wallet_transactions WHERE branch_id = ? AND type = 'earn' AND created_at >= m AND created_at < m + interval '1 month'), 0) AS earned,
                COALESCE((SELECT -SUM(amount) FROM wallet_transactions WHERE branch_id = ? AND type = 'redeem' AND created_at >= m AND created_at < m + interval '1 month'), 0) AS redeemed
            FROM generate_series(?::date, ?::date, interval '1 month') m ORDER BY m",
            [$branch->id, $branch->id, $monthStart->subMonthsNoOverflow(5)->toDateString(), $monthStart->toDateString()]);

        return view('panel.dashboard', [
            'branch' => $branch,
            'counter' => $counter->status($branch),
            'subscription' => $plans->activeSubscription($branch),
            'wallets' => $wallets,
            'tx' => $tx,
            'returning' => $returning,
            'pending' => ApprovalRequest::query()->where('branch_id', $branch->id)
                ->where('status', ApprovalRequest::STATUS_PENDING)->count(),
            'topCustomers' => $topCustomers,
            'daily' => $daily,
            'monthly' => $monthly,
        ]);
    }
}
