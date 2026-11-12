<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\PanelUser;
use App\Models\StaffPermission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Şube yöneticisi: çalışanlar. Çalışan tek şubeye bağlıdır ve yalnızca işaretlenen
 * yetkilerle işlem yapar. Hesaplar silinmez, pasifleştirilir (işlem kayıtlarında adı kalır).
 */
class StaffController extends Controller
{
    public const PERMISSIONS = [
        PanelUser::PERM_LOOKUP => ['Müşteri numarası sorgulama', 'Ad soyad ve bu şubedeki bakiyeyi görür'],
        PanelUser::PERM_EARN => ['Ödül kazandırma', 'Ödeme sonrası kumbaraya ödül ekler, kampanya seçer'],
        PanelUser::PERM_REDEEM => ['Ödül kullandırma', 'Müşteri onayıyla ödül kullanım isteği gönderir'],
        PanelUser::PERM_VIEW_CUSTOMERS => ['Müşterileri görüntüleme', 'Müşteri listesi ve işlem geçmişleri'],
        PanelUser::PERM_VIEW_REPORTS => ['Raporları görüntüleme', 'Şube raporları ve CSV dışa aktarma'],
    ];

    private function branch(Request $request): Branch
    {
        return $request->attributes->get('branch');
    }

    private function manager(): PanelUser
    {
        /** @var PanelUser */
        return Auth::guard('web')->user();
    }

    private function find(Request $request, int $id): PanelUser
    {
        return PanelUser::query()->where('branch_id', $this->branch($request)->id)
            ->where('role', PanelUser::ROLE_STAFF)->with('permissions')->findOrFail($id);
    }

    public function index(Request $request): View
    {
        $branch = $this->branch($request);

        $staff = PanelUser::query()->where('branch_id', $branch->id)->where('role', PanelUser::ROLE_STAFF)
            ->with('permissions')->orderByRaw("CASE status WHEN 'active' THEN 0 ELSE 1 END")->orderBy('first_name')->get();

        $txCounts = DB::table('wallet_transactions')->where('branch_id', $branch->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->whereIn('performed_by', $staff->pluck('id'))
            ->selectRaw('performed_by, COUNT(*) AS cnt')->groupBy('performed_by')->pluck('cnt', 'performed_by');

        return view('panel.staff.index', [
            'staff' => $staff,
            'txCounts' => $txCounts,
            'permissions' => self::PERMISSIONS,
        ]);
    }

    public function create(): View
    {
        return view('panel.staff.form', ['member' => null, 'permissions' => self::PERMISSIONS]);
    }

    public function edit(Request $request, int $staff): View
    {
        return view('panel.staff.form', ['member' => $this->find($request, $staff), 'permissions' => self::PERMISSIONS]);
    }

    public function store(Request $request): RedirectResponse
    {
        $branch = $this->branch($request);
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $data = $request->validate($this->rules(null) + [
            'password' => ['required', 'string', 'confirmed', Password::min(10)],
        ], $this->messages(), $this->attributes());

        $member = DB::transaction(function () use ($branch, $data) {
            $member = PanelUser::query()->create([
                'role' => PanelUser::ROLE_STAFF,
                'business_id' => $branch->business_id,
                'branch_id' => $branch->id,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'status' => 'active',
            ]);
            $this->syncPermissions($member, $data['permissions'] ?? []);
            AuditLog::record($this->manager(), 'staff.created', $member, ['permissions' => $data['permissions'] ?? []]);

            return $member;
        });

        return redirect()->route('panel.staff')->with('status', $member->fullName().' eklendi. '.$member->email.' adresi ve belirlediğiniz şifreyle panele giriş yapabilir.');
    }

    public function update(Request $request, int $staff): RedirectResponse
    {
        $member = $this->find($request, $staff);
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $data = $request->validate($this->rules($member) + [
            'password' => ['nullable', 'string', 'confirmed', Password::min(10)],
        ], $this->messages(), $this->attributes());

        DB::transaction(function () use ($member, $data) {
            $before = $member->permissions->pluck('permission')->sort()->values()->all();
            $member->fill([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
            ]);
            if (! empty($data['password'])) {
                $member->password = $data['password'];
            }
            $member->save();
            if (! empty($data['password'])) {
                $member->tokens()->delete();
            }
            $this->syncPermissions($member, $data['permissions'] ?? []);
            AuditLog::record($this->manager(), 'staff.updated', $member, [
                'permissions_from' => $before,
                'permissions_to' => array_values($data['permissions'] ?? []),
                'password_changed' => ! empty($data['password']),
            ]);
        });

        return redirect()->route('panel.staff')->with('status', $member->fullName().' güncellendi.'.(! empty($data['password']) ? ' Yeni şifre hemen geçerli.' : ''));
    }

    public function toggle(Request $request, int $staff): RedirectResponse
    {
        $member = $this->find($request, $staff);
        $to = $member->status === 'active' ? 'passive' : 'active';

        $member->update(['status' => $to]);
        if ($to === 'passive') {
            $member->tokens()->delete(); // açık API oturumları kapanır; web oturumu ilk istekte düşer
        }
        AuditLog::record($this->manager(), 'staff.status_changed', $member, ['to' => $to]);

        return back()->with('status', $to === 'passive'
            ? $member->fullName().' pasifleştirildi; artık giriş yapamaz.'
            : $member->fullName().' yeniden aktif edildi.');
    }

    private function syncPermissions(PanelUser $member, array $perms): void
    {
        $perms = array_values(array_intersect(array_keys(self::PERMISSIONS), $perms));
        StaffPermission::query()->where('panel_user_id', $member->id)->whereNotIn('permission', $perms ?: ['__none__'])->delete();
        foreach ($perms as $p) {
            StaffPermission::query()->firstOrCreate(['panel_user_id' => $member->id, 'permission' => $p]);
        }
    }

    private function rules(?PanelUser $member): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', function ($attr, $value, $fail) use ($member) {
                $exists = PanelUser::query()->whereRaw('lower(email) = ?', [$value])
                    ->when($member, fn ($q) => $q->where('id', '<>', $member->id))->exists();
                if ($exists) {
                    $fail('Bu e-posta adresiyle başka bir panel hesabı var.');
                }
            }],
            'phone' => ['nullable', 'string', 'regex:/^[0-9+ ]{10,20}$/'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [Rule::in(array_keys(self::PERMISSIONS))],
        ];
    }

    private function messages(): array
    {
        return ['phone.regex' => 'Telefonu yalnızca rakamlarla yazın.'];
    }

    private function attributes(): array
    {
        return ['first_name' => 'ad', 'last_name' => 'soyad', 'email' => 'e-posta', 'phone' => 'telefon', 'password' => 'şifre'];
    }
}
