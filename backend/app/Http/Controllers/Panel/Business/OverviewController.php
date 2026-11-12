<?php

namespace App\Http\Controllers\Panel\Business;

use App\Domain\Billing\PlanResolver;
use App\Models\AuditLog;
use App\Models\Category;
use App\Support\ImageStore;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** İşletme yöneticisi: genel bakış (tüm şubeler) ve işletme bilgileri. */
class OverviewController extends BaseController
{
    /** Şube bazında karşılaştırma satırları (son 30 gün + bugünkü durum). */
    public static function branchRows(int $businessId): array
    {
        $now = CarbonImmutable::now();

        return DB::select("SELECT b.id, b.name, b.slug, b.status, b.reward_rate, b.rating_avg, b.rating_count, b.created_at,
                c.name AS city, d.name AS district,
                (SELECT p.first_name || ' ' || p.last_name FROM panel_users p WHERE p.branch_id = b.id AND p.role = 'branch_manager' AND p.status = 'active' LIMIT 1) AS manager,
                (SELECT COUNT(*) FROM wallets w WHERE w.branch_id = b.id) AS customers,
                (SELECT COALESCE(SUM(balance), 0) FROM wallets w WHERE w.branch_id = b.id) AS balance,
                (SELECT unique_customers FROM monthly_branch_counters m WHERE m.branch_id = b.id AND m.period = ?) AS monthly,
                (SELECT COUNT(*) FROM wallet_transactions t WHERE t.branch_id = b.id AND t.created_at >= ?) AS tx30,
                (SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions t WHERE t.branch_id = b.id AND t.type = 'earn' AND t.created_at >= ?) AS earned30,
                (SELECT COALESCE(-SUM(amount), 0) FROM wallet_transactions t WHERE t.branch_id = b.id AND t.type = 'redeem' AND t.created_at >= ?) AS redeemed30,
                (SELECT MAX(ends_at) FROM subscriptions s WHERE s.branch_id = b.id AND s.status = 'active' AND s.starts_at <= ? AND s.ends_at > ?) AS pro_until
            FROM branches b
            JOIN cities c ON c.id = b.city_id
            JOIN districts d ON d.id = b.district_id
            WHERE b.business_id = ? AND b.status <> 'deleted'
            ORDER BY CASE b.status WHEN 'active' THEN 0 ELSE 1 END, b.name",
            [$now->startOfMonth()->toDateString(), $now->subDays(30), $now->subDays(30), $now->subDays(30), $now, $now, $businessId]);
    }

    public function index(Request $request, PlanResolver $plans): View
    {
        $business = $this->business($request);
        $rows = self::branchRows($business->id);
        $now = CarbonImmutable::now();
        $ids = array_column($rows, 'id') ?: [0];
        $in = implode(',', array_map('intval', $ids));

        $monthly = DB::select("SELECT m::date AS month,
                COALESCE((SELECT SUM(amount) FROM wallet_transactions WHERE branch_id IN ($in) AND type = 'earn' AND created_at >= m AND created_at < m + interval '1 month'), 0) AS earned,
                COALESCE((SELECT -SUM(amount) FROM wallet_transactions WHERE branch_id IN ($in) AND type = 'redeem' AND created_at >= m AND created_at < m + interval '1 month'), 0) AS redeemed
            FROM generate_series(?::date, ?::date, interval '1 month') m ORDER BY m",
            [$now->startOfMonth()->subMonthsNoOverflow(5)->toDateString(), $now->startOfMonth()->toDateString()]);

        $totalCustomers = DB::table('wallets')->whereIn('branch_id', $ids)->distinct()->count('customer_id');

        return view('panel.business.overview', [
            'business' => $business,
            'rows' => $rows,
            'limit' => $plans->freeMonthlyLimit(),
            'totalCustomers' => $totalCustomers,
            'monthly' => $monthly,
        ]);
    }

    public function profile(Request $request): View
    {
        return view('panel.business.profile', [
            'business' => $this->business($request)->load('category'),
            'categories' => Category::query()->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $business = $this->business($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [
            'logo.mimes' => 'Logo JPG, PNG veya WEBP olmalıdır.',
            'logo.max' => 'Logo en fazla 4 MB olabilir.',
        ], ['name' => 'işletme adı', 'description' => 'açıklama', 'category_id' => 'mutfak', 'logo' => 'logo']);

        $before = $business->only(['name', 'category_id']);
        $business->fill([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'category_id' => $data['category_id'],
        ]);
        if ($request->boolean('remove_logo') && $business->logo_path) {
            ImageStore::delete($business->logo_path);
            $business->logo_path = null;
        }
        if ($request->hasFile('logo')) {
            ImageStore::delete($business->logo_path);
            $business->logo_path = ImageStore::store($request->file('logo'), 'isletmeler/'.$business->id, 600);
        }
        $business->save();
        AuditLog::record($this->owner(), 'business.profile_updated', $business, ['before' => $before]);

        return back()->with('status', 'İşletme bilgileri kaydedildi. Sitede ve uygulamada hemen güncellenir.');
    }
}
