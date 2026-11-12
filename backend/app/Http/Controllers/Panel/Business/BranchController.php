<?php

namespace App\Http\Controllers\Panel\Business;

use App\Domain\Billing\PlanResolver;
use App\Domain\Counter\MonthlyCounterService;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\City;
use App\Models\District;
use App\Models\PaymentNotification;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * İşletme yöneticisi: şubeler. Her şube bağımsız bir restorandır (ayrı kumbara, oran, sayaç, paket).
 * Yeni şubenin ödül oranı %0'dır; oranı şube yöneticisi belirler.
 * İşlem görmüş şube silinemez (müşteri bakiyeleri ve kayıtlar korunur) — pasifleştirilir.
 */
class BranchController extends BaseController
{
    public function index(Request $request): View
    {
        return view('panel.business.branches.index', [
            'business' => $this->business($request),
            'rows' => OverviewController::branchRows($this->business($request)->id),
        ]);
    }

    public function show(Request $request, int $branch, PlanResolver $plans, MonthlyCounterService $counter): View
    {
        $b = $this->branchOf($request, $branch)->load(['city', 'district', 'hours', 'photos', 'manager']);

        return view('panel.business.branches.show', [
            'business' => $this->business($request),
            'branch' => $b,
            'counter' => $counter->status($b),
            'subscription' => $plans->activeSubscription($b),
            'staffCount' => DB::table('panel_users')->where('branch_id', $b->id)->where('role', 'staff')->where('status', 'active')->count(),
            'stats' => DB::selectOne("SELECT
                    (SELECT COUNT(*) FROM wallets WHERE branch_id = ?) AS customers,
                    (SELECT COALESCE(SUM(balance), 0) FROM wallets WHERE branch_id = ?) AS balance,
                    (SELECT COUNT(*) FROM wallet_transactions WHERE branch_id = ? AND created_at >= now() - interval '30 days') AS tx30,
                    (SELECT COUNT(*) FROM campaigns WHERE branch_id = ? AND status = 'active' AND starts_at <= now() AND ends_at > now()) AS campaigns",
                [$b->id, $b->id, $b->id, $b->id]),
            'hasTransactions' => DB::table('wallet_transactions')->where('branch_id', $b->id)->exists(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('panel.business.branches.create', [
            'business' => $this->business($request),
            'cities' => City::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** İl seçilince ilçe listesi (form içinde kullanılır). */
    public function districts(int $city): JsonResponse
    {
        return response()->json(District::query()->where('city_id', $city)->orderBy('name')->get(['id', 'name']));
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->business($request);
        $request->merge(['phone' => $request->filled('phone') ? preg_replace('/\s+/', '', (string) $request->input('phone')) : null]);

        $data = $request->validate([
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'district_id' => ['required', 'integer', Rule::exists('districts', 'id')->where('city_id', (int) $request->input('city_id'))],
            'name' => ['required', 'string', 'max:150'],
            'address' => ['required', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'regex:/^[0-9+]{10,20}$/'],
        ], [
            'district_id.exists' => 'Seçilen ilçe bu ile ait değil.',
            'phone.regex' => 'Telefonu yalnızca rakamlarla yazın.',
        ], ['city_id' => 'il', 'district_id' => 'ilçe', 'name' => 'şube adı', 'address' => 'adres', 'phone' => 'telefon']);

        $city = City::query()->findOrFail($data['city_id']);
        $district = District::query()->findOrFail($data['district_id']);

        // Adres: /restoran/{işletme}/{il-ilçe}; aynı ilçede ikinci şube için sona sayı eklenir
        $base = Str::slug($city->slug.'-'.$district->slug) ?: 'sube';
        $slug = $base;
        for ($i = 2; Branch::query()->where('business_id', $business->id)->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        $branch = Branch::query()->create([
            'business_id' => $business->id,
            'name' => $data['name'],
            'slug' => $slug,
            'city_id' => $city->id,
            'district_id' => $district->id,
            'address' => $data['address'],
            'phone' => $data['phone'] ?? null,
            'reward_rate' => 0,
            'status' => 'active',
        ]);
        AuditLog::record($this->owner(), 'branch.created', $branch, ['slug' => $slug]);

        return redirect()->route('biz.managers.create', ['sube' => $branch->id])
            ->with('status', '"'.$branch->name.'" şubesi eklendi. Şimdi şube yöneticisi hesabını oluşturun; ödül oranını şube yöneticisi belirler (yeni şubede %0).');
    }

    public function updateStatus(Request $request, int $branch): RedirectResponse
    {
        $b = $this->branchOf($request, $branch);
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'passive'])]]);
        if ($b->status !== $data['status']) {
            $old = $b->status;
            $b->update(['status' => $data['status']]);
            AuditLog::record($this->owner(), 'branch.status_changed', $b, ['from' => $old, 'to' => $data['status'], 'by' => 'business_owner']);
        }

        return back()->with('status', $data['status'] === 'passive'
            ? '"'.$b->name.'" pasif yapıldı: sitede ve uygulamada görünmez, personel işlem yapamaz. Müşteri bakiyeleri korunur.'
            : '"'.$b->name.'" yeniden aktif.');
    }

    /** Yalnızca hiç işlem görmemiş şube silinebilir. */
    public function destroy(Request $request, int $branch): RedirectResponse
    {
        $b = $this->branchOf($request, $branch);
        if (DB::table('wallet_transactions')->where('branch_id', $b->id)->exists()) {
            return back()->with('error', 'Bu şubede işlem yapılmış; müşteri bakiyeleri ve kayıtlar korunmak zorunda olduğu için silinemez. Şubeyi pasif yapabilirsiniz.');
        }
        if (Subscription::query()->where('branch_id', $b->id)->where('status', 'active')->where('ends_at', '>', now())->exists()
            || PaymentNotification::query()->where('branch_id', $b->id)->where('status', 'pending')->exists()) {
            return back()->with('error', 'Şubenin süren bir paketi veya onay bekleyen havale bildirimi var; silinemez.');
        }

        DB::transaction(function () use ($b) {
            $b->update(['status' => 'deleted']);
            // Şubeye bağlı panel hesapları kapatılır
            DB::table('panel_users')->where('branch_id', $b->id)->update(['status' => 'passive', 'updated_at' => now()]);
            AuditLog::record($this->owner(), 'branch.deleted', $b);
        });

        return redirect()->route('biz.branches')->with('status', '"'.$b->name.'" şubesi silindi.');
    }
}
