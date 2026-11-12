<?php

namespace App\Http\Controllers\Admin;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends AdminController
{
    public function __invoke(): View
    {
        $now = CarbonImmutable::now();

        $businesses = DB::table('businesses')->selectRaw('status, COUNT(*) AS cnt')->groupBy('status')->pluck('cnt', 'status');

        $branchTotal = DB::table('branches')->where('status', '<>', 'deleted')->count();
        $branchActive = DB::table('branches')->join('businesses', 'businesses.id', '=', 'branches.business_id')
            ->where('branches.status', 'active')->where('businesses.status', 'approved')->count();

        $proBranches = DB::table('subscriptions')
            ->where('status', 'active')->where('starts_at', '<=', $now)->where('ends_at', '>', $now)
            ->distinct()->count('branch_id');

        $customers = DB::table('customers')->whereNull('deleted_at');
        $customerTotal = (clone $customers)->count();
        $customerPassive = (clone $customers)->where('status', 'passive')->count();
        $customerActive30 = DB::table('wallet_transactions')->where('created_at', '>=', $now->subDays(30))->distinct()->count('customer_id');

        $tx = DB::selectOne("SELECT COUNT(*) AS cnt,
                COALESCE(SUM(CASE WHEN type = 'earn' THEN amount END), 0) AS earned,
                COALESCE(-SUM(CASE WHEN type = 'redeem' THEN amount END), 0) AS redeemed,
                COUNT(*) FILTER (WHERE created_at >= ?) AS cnt30
            FROM wallet_transactions", [$now->subDays(30)]);

        $balance = DB::table('wallets')->sum('balance');

        // ---- Grafik verileri (boş günler/aylar sıfırla doldurulur)
        $dayFrom = $now->startOfDay()->subDays(29);
        $daily = DB::select("SELECT d::date AS day, COUNT(t.id) AS cnt
            FROM generate_series(?::date, ?::date, interval '1 day') d
            LEFT JOIN wallet_transactions t ON t.created_at >= d AND t.created_at < d + interval '1 day'
            GROUP BY d ORDER BY d", [$dayFrom->toDateString(), $now->toDateString()]);

        $monthFrom = $now->startOfMonth()->subMonthsNoOverflow(5);
        $monthly = DB::select("SELECT m::date AS month,
                COALESCE((SELECT SUM(amount) FROM wallet_transactions WHERE type = 'earn' AND created_at >= m AND created_at < m + interval '1 month'), 0) AS earned,
                COALESCE((SELECT -SUM(amount) FROM wallet_transactions WHERE type = 'redeem' AND created_at >= m AND created_at < m + interval '1 month'), 0) AS redeemed,
                (SELECT COUNT(*) FROM customers WHERE created_at >= m AND created_at < m + interval '1 month') AS new_customers,
                COALESCE((SELECT SUM(amount) FROM payment_notifications WHERE status = 'approved' AND purpose = 'subscription' AND reviewed_at >= m AND reviewed_at < m + interval '1 month'), 0) AS rev_sub,
                COALESCE((SELECT SUM(amount) FROM payment_notifications WHERE status = 'approved' AND purpose = 'sponsorship' AND reviewed_at >= m AND reviewed_at < m + interval '1 month'), 0) AS rev_spo
            FROM generate_series(?::date, ?::date, interval '1 month') m ORDER BY m", [$monthFrom->toDateString(), $now->startOfMonth()->toDateString()]);

        // ---- İşletme grafikleri
        // Aylık yeni işletme (başvuru tarihi) ve yeni şube; ay sonundaki Profesyonel şube sayısı.
        // İptal edilen abonelikler hatalı aktivasyon sayıldığı için hesaba katılmaz.
        $bizMonthly = DB::select("SELECT m::date AS month,
                (SELECT COUNT(*) FROM businesses WHERE status NOT IN ('pending', 'rejected') AND created_at >= m AND created_at < m + interval '1 month') AS new_businesses,
                (SELECT COUNT(*) FROM branches WHERE status <> 'deleted' AND created_at >= m AND created_at < m + interval '1 month') AS new_branches,
                (SELECT COUNT(DISTINCT branch_id) FROM subscriptions
                  WHERE status IN ('active', 'expired')
                    AND starts_at <= LEAST(m + interval '1 month', now()) AND ends_at > LEAST(m + interval '1 month', now())) AS pro_branches
            FROM generate_series(?::date, ?::date, interval '1 month') m ORDER BY m", [$monthFrom->toDateString(), $now->startOfMonth()->toDateString()]);

        $topBranches = DB::select("SELECT b.id, b.name, bu.id AS business_id, bu.name AS business_name, c.name AS city, COUNT(*) AS cnt
            FROM wallet_transactions t
            JOIN branches b ON b.id = t.branch_id
            JOIN businesses bu ON bu.id = b.business_id
            JOIN cities c ON c.id = b.city_id
            WHERE t.created_at >= ?
            GROUP BY b.id, b.name, bu.id, bu.name, c.name
            ORDER BY cnt DESC, b.id
            LIMIT 10", [$now->subDays(30)]);

        $byCategory = DB::select("SELECT COALESCE(c.name, 'Seçilmemiş') AS name, COUNT(*) AS cnt
            FROM branches b
            JOIN businesses bu ON bu.id = b.business_id
            LEFT JOIN categories c ON c.id = bu.category_id
            WHERE b.status = 'active' AND bu.status = 'approved'
            GROUP BY c.name ORDER BY cnt DESC, name");

        return view('admin.dashboard', [
            'bizMonthly' => $bizMonthly,
            'topBranches' => $topBranches,
            'byCategory' => $byCategory,
            'daily' => $daily,
            'monthly' => $monthly,
            'todo' => [
                'applications' => (int) ($businesses['pending'] ?? 0),
                'payments' => DB::table('payment_notifications')->where('status', 'pending')->count(),
                'reports' => DB::table('review_reports')->where('status', 'open')->distinct()->count('review_id'),
            ],
            'businesses' => $businesses,
            'businessTotal' => $businesses->sum(),
            'branchTotal' => $branchTotal,
            'branchActive' => $branchActive,
            'proBranches' => $proBranches,
            'freeBranches' => max(0, $branchActive - $proBranches),
            'customerTotal' => $customerTotal,
            'customerPassive' => $customerPassive,
            'customerActive30' => $customerActive30,
            'tx' => $tx,
            'balance' => $balance,
            'sponsoredThisMonth' => DB::table('sponsorships')->where('month', $now->startOfMonth()->toDateString())
                ->whereIn('status', ['pending', 'active'])->count(),
        ]);
    }
}
