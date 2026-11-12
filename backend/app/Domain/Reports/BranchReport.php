<?php

namespace App\Domain\Reports;

use App\Models\Branch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Şube raporu: seçilen tarih aralığında işlem, müşteri ve ödül istatistikleri.
 * "Ziyaret" = ödül kazanma veya kullanma işlemi (düzeltmeler ziyaret sayılmaz).
 * "Yeni müşteri" = bu şubede ilk ödülünü bu dönemde kazanan müşteri.
 * DB bağlantısı Europe/Istanbul saat diliminde olduğu için date_trunc yerel güne göre çalışır.
 */
class BranchReport
{
    public const MAX_DAYS = 366;

    public function __construct(
        public readonly Branch $branch,
        public readonly CarbonImmutable $from,   // dahil, gün başı
        public readonly CarbonImmutable $to,     // dahil, gün (son gün)
    ) {}

    /** Gün sayısına göre dönem: ≤ 45 gün günlük, ≤ 200 gün haftalık, üstü aylık. */
    public function granularity(): string
    {
        $days = $this->from->diffInDays($this->to) + 1;

        return $days <= 45 ? 'day' : ($days <= 200 ? 'week' : 'month');
    }

    private function end(): CarbonImmutable
    {
        return $this->to->addDay()->startOfDay();
    }

    public function summary(): object
    {
        $row = DB::selectOne("SELECT
                COUNT(*) AS tx,
                COUNT(*) FILTER (WHERE type = 'earn') AS earn_count,
                COUNT(*) FILTER (WHERE type = 'redeem') AS redeem_count,
                COUNT(*) FILTER (WHERE type = 'correction') AS correction_count,
                COUNT(DISTINCT customer_id) FILTER (WHERE type IN ('earn', 'redeem')) AS customers,
                COALESCE(SUM(spend_amount), 0) AS spend,
                COALESCE(SUM(amount) FILTER (WHERE type = 'earn'), 0) AS earned,
                COALESCE(-SUM(amount) FILTER (WHERE type = 'redeem'), 0) AS redeemed,
                COALESCE(SUM(amount) FILTER (WHERE type = 'correction'), 0) AS corrected
            FROM wallet_transactions WHERE branch_id = ? AND created_at >= ? AND created_at < ?",
            [$this->branch->id, $this->from, $this->end()]);

        $new = DB::selectOne("SELECT COUNT(*) AS cnt FROM (
                SELECT customer_id, MIN(created_at) AS first_at FROM wallet_transactions
                WHERE branch_id = ? AND type = 'earn' GROUP BY customer_id) f
            WHERE first_at >= ? AND first_at < ?", [$this->branch->id, $this->from, $this->end()]);

        $row->new_customers = (int) $new->cnt;
        $row->returning_customers = max(0, (int) $row->customers - $row->new_customers);
        $row->avg_spend = $row->earn_count > 0 ? round((float) $row->spend / $row->earn_count, 2) : 0;

        // Bugünkü durum (aralıktan bağımsız)
        $w = DB::selectOne('SELECT COUNT(*) AS customers, COALESCE(SUM(balance), 0) AS balance FROM wallets WHERE branch_id = ?', [$this->branch->id]);
        $row->total_customers = (int) $w->customers;
        $row->balance = $w->balance;

        return $row;
    }

    /** Dönem satırları (boş dönemler sıfırla). */
    public function periods(): array
    {
        $g = $this->granularity();

        $rows = collect(DB::select("SELECT date_trunc('$g', created_at)::date AS p,
                COUNT(*) AS tx,
                COUNT(DISTINCT customer_id) FILTER (WHERE type IN ('earn', 'redeem')) AS customers,
                COALESCE(SUM(spend_amount), 0) AS spend,
                COALESCE(SUM(amount) FILTER (WHERE type = 'earn'), 0) AS earned,
                COALESCE(-SUM(amount) FILTER (WHERE type = 'redeem'), 0) AS redeemed
            FROM wallet_transactions WHERE branch_id = ? AND created_at >= ? AND created_at < ?
            GROUP BY 1", [$this->branch->id, $this->from, $this->end()]))->keyBy(fn ($r) => (string) $r->p);

        $news = collect(DB::select("SELECT date_trunc('$g', first_at)::date AS p, COUNT(*) AS cnt FROM (
                SELECT customer_id, MIN(created_at) AS first_at FROM wallet_transactions
                WHERE branch_id = ? AND type = 'earn' GROUP BY customer_id) f
            WHERE first_at >= ? AND first_at < ? GROUP BY 1", [$this->branch->id, $this->from, $this->end()]))
            ->pluck('cnt', 'p');

        $out = [];
        $cursor = match ($g) {
            'day' => $this->from,
            'week' => $this->from->startOfWeek(CarbonImmutable::MONDAY), // PostgreSQL date_trunc('week') da Pazartesi
            'month' => $this->from->startOfMonth(),
        };
        while ($cursor->lte($this->to)) {
            $key = $cursor->toDateString();
            $r = $rows[$key] ?? null;
            $new = (int) ($news[$key] ?? 0);
            $customers = (int) ($r->customers ?? 0);
            $out[] = (object) [
                'start' => $cursor,
                'tx' => (int) ($r->tx ?? 0),
                'customers' => $customers,
                'new' => min($new, $customers),
                'returning' => max(0, $customers - $new),
                'spend' => $r->spend ?? 0,
                'earned' => $r->earned ?? 0,
                'redeemed' => $r->redeemed ?? 0,
            ];
            $cursor = match ($g) {
                'day' => $cursor->addDay(),
                'week' => $cursor->addWeek(),
                'month' => $cursor->addMonthNoOverflow(),
            };
        }

        return $out;
    }

    public function campaigns(): array
    {
        return DB::select("SELECT c.id, COALESCE(c.title, 'Kampanyasız (şube oranı)') AS title, c.type, c.reward_rate,
                COUNT(t.id) AS tx, COUNT(DISTINCT t.customer_id) AS customers,
                COALESCE(SUM(t.spend_amount), 0) AS spend, COALESCE(SUM(t.amount), 0) AS earned
            FROM wallet_transactions t
            LEFT JOIN campaigns c ON c.id = t.campaign_id
            WHERE t.branch_id = ? AND t.type = 'earn' AND t.created_at >= ? AND t.created_at < ?
            GROUP BY c.id, c.title, c.type, c.reward_rate
            ORDER BY (c.id IS NULL), COUNT(t.id) DESC", [$this->branch->id, $this->from, $this->end()]);
    }

    public function topCustomers(int $limit = 20): array
    {
        return DB::select("SELECT c.customer_no, c.first_name, c.last_name,
                COUNT(t.id) FILTER (WHERE t.type IN ('earn', 'redeem')) AS visits,
                COALESCE(SUM(t.spend_amount), 0) AS spend,
                COALESCE(SUM(t.amount) FILTER (WHERE t.type = 'earn'), 0) AS earned,
                COALESCE(-SUM(t.amount) FILTER (WHERE t.type = 'redeem'), 0) AS redeemed,
                w.balance
            FROM wallet_transactions t
            JOIN customers c ON c.id = t.customer_id
            JOIN wallets w ON w.id = t.wallet_id
            WHERE t.branch_id = ? AND t.created_at >= ? AND t.created_at < ?
            GROUP BY c.customer_no, c.first_name, c.last_name, w.balance
            ORDER BY visits DESC, spend DESC LIMIT ?", [$this->branch->id, $this->from, $this->end(), $limit]);
    }

    /** Personel bazında işlem sayıları (yalnızca şube yöneticisine gösterilir). */
    public function staff(): array
    {
        return DB::select("SELECT p.first_name, p.last_name, p.role,
                COUNT(*) FILTER (WHERE t.type = 'earn') AS earn_count,
                COUNT(*) FILTER (WHERE t.type = 'redeem') AS redeem_count,
                COALESCE(SUM(t.amount) FILTER (WHERE t.type = 'earn'), 0) AS earned
            FROM wallet_transactions t JOIN panel_users p ON p.id = t.performed_by
            WHERE t.branch_id = ? AND t.created_at >= ? AND t.created_at < ?
            GROUP BY p.id, p.first_name, p.last_name, p.role ORDER BY COUNT(*) DESC", [$this->branch->id, $this->from, $this->end()]);
    }
}
