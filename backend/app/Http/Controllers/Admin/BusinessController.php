<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Billing\PlanResolver;
use App\Domain\Platform\BusinessReviewService;
use App\Models\Branch;
use App\Models\Business;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** İşletmeler, başvurular ve şubeler. */
class BusinessController extends AdminController
{
    public function __construct(private readonly BusinessReviewService $service) {}

    public function index(Request $request): View
    {
        $status = (string) $request->query('durum', '');
        $q = trim((string) $request->query('q', ''));

        $query = Business::query()
            ->with(['category', 'owner'])
            ->withCount(['branches' => fn ($b) => $b->where('status', '<>', 'deleted')]);

        if (array_key_exists($status, BusinessReviewService::BUSINESS_STATUSES)) {
            $query->where('status', $status);
        }

        if ($q !== '') {
            $like = $this->like($q);
            $query->where(function ($w) use ($like) {
                $w->whereRaw('lower(name) LIKE ?', [$like])
                    ->orWhereRaw('lower(legal_name) LIKE ?', [$like])
                    ->orWhereRaw('tax_number LIKE ?', [$like])
                    ->orWhereHas('owner', fn ($o) => $o->whereRaw('lower(email) LIKE ?', [$like]));
            });
        }

        // Başvurular en eskiden yeniye (sırayla incelensin), diğerleri en yeniden eskiye.
        $status === 'pending' ? $query->orderBy('created_at') : $query->orderByDesc('created_at');

        return view('admin.businesses.index', [
            'businesses' => $query->paginate(25)->withQueryString(),
            'status' => $status,
            'q' => $q,
            'counts' => DB::table('businesses')->selectRaw('status, COUNT(*) AS cnt')->groupBy('status')->pluck('cnt', 'status'),
            'statuses' => BusinessReviewService::BUSINESS_STATUSES,
        ]);
    }

    public function show(Business $business, PlanResolver $plans): View
    {
        $business->load(['category', 'owner']);
        $period = now()->startOfMonth()->toDateString();

        $branches = Branch::query()
            ->where('business_id', $business->id)
            ->with(['city', 'district', 'manager'])
            ->orderBy('id')
            ->get();

        $counters = DB::table('monthly_branch_counters')->where('period', $period)
            ->whereIn('branch_id', $branches->pluck('id'))->pluck('unique_customers', 'branch_id');

        $customers = DB::table('wallets')->whereIn('branch_id', $branches->pluck('id'))
            ->selectRaw('branch_id, COUNT(*) AS cnt, SUM(balance) AS balance')->groupBy('branch_id')->get()->keyBy('branch_id');

        $branchRows = $branches->map(fn (Branch $b) => [
            'branch' => $b,
            'subscription' => $plans->activeSubscription($b),
            'monthly' => (int) ($counters[$b->id] ?? 0),
            'customers' => (int) ($customers[$b->id]->cnt ?? 0),
            'balance' => $customers[$b->id]->balance ?? 0,
        ]);

        return view('admin.businesses.show', [
            'business' => $business,
            'branchRows' => $branchRows,
            'limit' => $plans->freeMonthlyLimit(),
            'statuses' => BusinessReviewService::BUSINESS_STATUSES,
            'branchStatuses' => BusinessReviewService::BRANCH_STATUSES,
        ]);
    }

    public function approve(Business $business): RedirectResponse
    {
        return $this->attempt(fn () => $this->service->approve($this->admin(), $business),
            'Başvuru onaylandı. İşletme yöneticisine e-posta gönderildi.');
    }

    public function reject(Request $request, Business $business): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']], [], ['reason' => 'ret gerekçesi']);

        return $this->attempt(fn () => $this->service->reject($this->admin(), $business, $data['reason']),
            'Başvuru reddedildi. İşletmeye gerekçesiyle birlikte e-posta gönderildi.');
    }

    public function updateStatus(Request $request, Business $business): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'suspended', 'closed'])],
            'reason' => ['nullable', 'string', 'max:1000'],
        ], [], ['status' => 'durum', 'reason' => 'gerekçe']);

        return $this->attempt(fn () => $this->service->setBusinessStatus($this->admin(), $business, $data['status'], $data['reason'] ?? null),
            'İşletmenin durumu güncellendi.');
    }

    public function updateBranchStatus(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'passive'])],
            'reason' => ['nullable', 'string', 'max:1000'],
        ], [], ['status' => 'durum', 'reason' => 'gerekçe']);

        return $this->attempt(fn () => $this->service->setBranchStatus($this->admin(), $branch, $data['status'], $data['reason'] ?? null),
            'Şubenin durumu güncellendi.');
    }
}
