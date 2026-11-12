<?php

namespace App\Http\Controllers\Panel\Business;

use App\Http\Controllers\Panel\Concerns\ReportRange;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\PaymentNotification;
use App\Models\Subscription;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** İşletme yöneticisi: şube karşılaştırmalı raporlar ve abonelikler (salt okunur). */
class ReportController extends BaseController
{
    use ReportRange;

    /** Seçilen aralıkta şube başına rakamlar. */
    public static function compare(int $businessId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $end = $to->addDay()->startOfDay();

        return DB::select("SELECT b.id, b.name, b.status,
                COALESCE(t.tx, 0) AS tx, COALESCE(t.customers, 0) AS customers,
                COALESCE(n.new_customers, 0) AS new_customers,
                COALESCE(t.spend, 0) AS spend, COALESCE(t.earned, 0) AS earned, COALESCE(t.redeemed, 0) AS redeemed,
                (SELECT COALESCE(SUM(balance), 0) FROM wallets w WHERE w.branch_id = b.id) AS balance
            FROM branches b
            LEFT JOIN (
                SELECT branch_id, COUNT(*) AS tx,
                    COUNT(DISTINCT customer_id) FILTER (WHERE type IN ('earn', 'redeem')) AS customers,
                    SUM(spend_amount) AS spend,
                    SUM(amount) FILTER (WHERE type = 'earn') AS earned,
                    -SUM(amount) FILTER (WHERE type = 'redeem') AS redeemed
                FROM wallet_transactions WHERE created_at >= ? AND created_at < ? GROUP BY branch_id
            ) t ON t.branch_id = b.id
            LEFT JOIN (
                SELECT branch_id, COUNT(*) AS new_customers FROM (
                    SELECT branch_id, customer_id, MIN(created_at) AS first_at FROM wallet_transactions
                    WHERE type = 'earn' GROUP BY branch_id, customer_id) f
                WHERE first_at >= ? AND first_at < ? GROUP BY branch_id
            ) n ON n.branch_id = b.id
            WHERE b.business_id = ? AND b.status <> 'deleted'
            ORDER BY COALESCE(t.earned, 0) DESC, b.name", [$from, $end, $from, $end, $businessId]);
    }

    public function index(Request $request): View|StreamedResponse
    {
        $business = $this->business($request);
        [$from, $to, $preset, $error] = $this->range($request);
        $rows = self::compare($business->id, $from, $to);

        $totals = (object) [
            'tx' => array_sum(array_column($rows, 'tx')),
            'customers' => (int) DB::table('wallet_transactions')
                ->whereIn('branch_id', array_column($rows, 'id') ?: [0])
                ->whereIn('type', ['earn', 'redeem'])
                ->where('created_at', '>=', $from)->where('created_at', '<', $to->addDay()->startOfDay())
                ->distinct()->count('customer_id'),
            'new_customers' => array_sum(array_column($rows, 'new_customers')),
            'spend' => array_sum(array_map('floatval', array_column($rows, 'spend'))),
            'earned' => array_sum(array_map('floatval', array_column($rows, 'earned'))),
            'redeemed' => array_sum(array_map('floatval', array_column($rows, 'redeemed'))),
            'balance' => array_sum(array_map('floatval', array_column($rows, 'balance'))),
        ];

        if ($request->query('indir') === 'subeler') {
            AuditLog::record($this->owner(), 'report.exported', $business, ['table' => 'subeler', 'from' => $from->toDateString(), 'to' => $to->toDateString()]);
            $money = fn ($v) => number_format((float) $v, 2, ',', '');

            return response()->streamDownload(function () use ($rows, $totals, $money) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, ['Şube', 'İşlem', 'Farklı müşteri', 'Yeni müşteri', 'Harcama (TL)', 'Kazandırılan ödül (TL)', 'Kullanılan ödül (TL)', 'Güncel bakiye (TL)'], ';');
                foreach ($rows as $r) {
                    fputcsv($out, [$r->name, $r->tx, $r->customers, $r->new_customers, $money($r->spend), $money($r->earned), $money($r->redeemed), $money($r->balance)], ';');
                }
                fputcsv($out, ['TOPLAM', $totals->tx, $totals->customers, $totals->new_customers, $money($totals->spend), $money($totals->earned), $money($totals->redeemed), $money($totals->balance)], ';');
                fclose($out);
            }, 'kumbare-subeler-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return view('panel.business.reports', [
            'business' => $business,
            'rows' => $rows,
            'totals' => $totals,
            'from' => $from, 'to' => $to, 'preset' => $preset, 'presets' => self::presets(), 'rangeError' => $error,
        ]);
    }

    public function subscriptions(Request $request): View
    {
        $business = $this->business($request);
        $branches = Branch::query()->where('business_id', $business->id)->where('status', '<>', 'deleted')->orderBy('name')->get();
        $ids = $branches->pluck('id');

        return view('panel.business.subscriptions', [
            'branches' => $branches,
            'current' => Subscription::query()->whereIn('branch_id', $ids)->currentlyActive()->get()->keyBy('branch_id'),
            'history' => Subscription::query()->whereIn('branch_id', $ids)->with('branch')->orderByDesc('starts_at')->limit(50)->get(),
            'payments' => PaymentNotification::query()->whereIn('branch_id', $ids)->with('branch')->orderByDesc('id')->limit(30)->get(),
            'limit' => \App\Models\Setting::getInt('free_monthly_customer_limit', 100),
            'monthly' => DB::table('monthly_branch_counters')->whereIn('branch_id', $ids)
                ->where('period', now()->startOfMonth()->toDateString())->pluck('unique_customers', 'branch_id'),
        ]);
    }
}
