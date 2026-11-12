<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Müşteriler: arama, detay, pasifleştirme, hatalı doğum tarihini düzeltme. Bakiye değiştirilemez. */
class CustomerController extends AdminController
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('durum', '');

        $query = Customer::query()->with(['city', 'district']);

        if ($q !== '') {
            $digits = preg_replace('/\D/', '', $q);
            $like = $this->like($q);
            $query->where(function ($w) use ($q, $digits, $like) {
                if (ctype_digit($q)) {
                    $w->orWhere('customer_no', (int) $q);
                }
                $w->orWhereRaw("lower(first_name || ' ' || last_name) LIKE ?", [$like])
                    ->orWhereRaw('lower(email) LIKE ?', [$like]);
                if (strlen($digits) >= 4) {
                    $w->orWhere('phone', 'LIKE', '%'.$digits.'%');
                }
            });
        }

        if (in_array($status, ['active', 'passive'], true)) {
            $query->where('status', $status);
        } elseif ($status === 'deleted') {
            $query->onlyTrashed();
        }

        // Numarayla aranınca tam eşleşen müşteri, telefonunda o rakamlar geçenlerden önce gelir.
        if (ctype_digit($q)) {
            $query->orderByRaw('CASE WHEN customer_no = ? THEN 0 ELSE 1 END', [(int) $q]);
        }

        return view('admin.customers.index', [
            'customers' => $query->orderByDesc('customer_no')->paginate(25)->withQueryString(),
            'q' => $q,
            'status' => $status,
        ]);
    }

    public function show(int $customer): View
    {
        $customer = Customer::withTrashed()->with(['city', 'district'])->findOrFail($customer);

        $wallets = DB::table('wallets')
            ->join('branches', 'branches.id', '=', 'wallets.branch_id')
            ->join('businesses', 'businesses.id', '=', 'branches.business_id')
            ->where('wallets.customer_id', $customer->id)
            ->orderByDesc('wallets.updated_at')
            ->get(['wallets.*', 'branches.name AS branch_name', 'businesses.name AS business_name', 'businesses.id AS business_id']);

        $transactions = DB::table('wallet_transactions AS t')
            ->join('branches', 'branches.id', '=', 't.branch_id')
            ->join('businesses', 'businesses.id', '=', 'branches.business_id')
            ->where('t.customer_id', $customer->id)
            ->orderByDesc('t.id')
            ->limit(30)
            ->get(['t.*', 'branches.name AS branch_name', 'businesses.name AS business_name']);

        $birthDateChanges = AuditLog::query()
            ->where('action', 'customer.birth_date_corrected')
            ->where('subject_type', 'Customer')->where('subject_id', $customer->id)
            ->orderByDesc('id')->get();

        return view('admin.customers.show', compact('customer', 'wallets', 'transactions', 'birthDateChanges'));
    }

    public function updateStatus(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'passive'])],
            'reason' => ['nullable', 'string', 'max:1000'],
        ], [], ['status' => 'durum', 'reason' => 'gerekçe']);

        if ($customer->status !== $data['status']) {
            $old = $customer->status;
            DB::transaction(function () use ($customer, $data, $old) {
                $customer->forceFill(['status' => $data['status']])->save();
                if ($data['status'] === 'passive') {
                    $customer->tokens()->delete(); // uygulamadaki oturumları kapanır
                }
                AuditLog::record($this->admin(), 'customer.status_changed', $customer,
                    ['from' => $old, 'to' => $data['status'], 'reason' => $data['reason'] ?? null]);
            });
        }

        return back()->with('status', $data['status'] === 'passive'
            ? 'Müşteri pasifleştirildi; uygulamadaki oturumları kapatıldı.'
            : 'Müşteri yeniden aktif edildi.');
    }

    /** Müşteri doğum tarihini kendisi değiştiremez; hatalı girişi yalnızca platform yöneticisi düzeltir. */
    public function updateBirthDate(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'birth_date' => ['required', 'date', 'after_or_equal:1900-01-01', 'before:today'],
            'reason' => ['required', 'string', 'max:1000'],
        ], [], ['birth_date' => 'doğum tarihi', 'reason' => 'düzeltme gerekçesi']);

        $old = $customer->birth_date?->toDateString();
        if ($old === $data['birth_date']) {
            return back()->with('status', 'Doğum tarihi zaten bu değerde.');
        }

        DB::transaction(function () use ($customer, $data, $old) {
            // birth_date müşteri tarafından toplu atanamaz (fillable değil); bilinçli olarak forceFill.
            $customer->forceFill(['birth_date' => $data['birth_date']])->save();
            AuditLog::record($this->admin(), 'customer.birth_date_corrected', $customer,
                ['from' => $old, 'to' => $data['birth_date'], 'reason' => $data['reason']]);
        });

        return back()->with('status', 'Doğum tarihi düzeltildi ve denetim kaydına yazıldı.');
    }
}
