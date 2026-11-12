<?php

namespace App\Http\Controllers\Panel\Business;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\PanelUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * İşletme yöneticisi: şube yöneticisi hesapları. Her şubede tek aktif şube yöneticisi olur;
 * yöneticiyi değiştirmek için eskisi pasifleştirilip yenisi eklenir. Hesaplar silinmez.
 */
class ManagerController extends BaseController
{
    private function findManager(Request $request, int $id): PanelUser
    {
        return PanelUser::query()->where('business_id', $this->business($request)->id)
            ->where('role', PanelUser::ROLE_BRANCH_MANAGER)->with('branch')->findOrFail($id);
    }

    public function index(Request $request): View
    {
        $business = $this->business($request);

        return view('panel.business.managers.index', [
            'branches' => Branch::query()->where('business_id', $business->id)->where('status', '<>', 'deleted')
                ->with('manager')->orderBy('name')->get(),
            'managers' => PanelUser::query()->where('business_id', $business->id)
                ->where('role', PanelUser::ROLE_BRANCH_MANAGER)->with('branch')
                ->orderByRaw("CASE status WHEN 'active' THEN 0 ELSE 1 END")->orderBy('first_name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $business = $this->business($request);

        return view('panel.business.managers.form', [
            'member' => null,
            'branches' => Branch::query()->where('business_id', $business->id)->where('status', '<>', 'deleted')
                ->whereDoesntHave('manager')->orderBy('name')->get(),
            'selected' => (int) $request->query('sube'),
        ]);
    }

    public function edit(Request $request, int $manager): View
    {
        return view('panel.business.managers.form', ['member' => $this->findManager($request, $manager), 'branches' => collect(), 'selected' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->business($request);
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $data = $request->validate($this->rules(null) + [
            'branch_id' => ['required', 'integer'],
            'password' => ['required', 'string', 'confirmed', Password::min(10)],
        ], [], $this->attributes() + ['branch_id' => 'şube']);

        $branch = $this->branchOf($request, (int) $data['branch_id']);
        if ($branch->manager()->exists()) {
            return back()->withInput()->with('error', '"'.$branch->name.'" şubesinin zaten aktif bir yöneticisi var. Önce onu pasifleştirin.');
        }

        $member = PanelUser::query()->create([
            'role' => PanelUser::ROLE_BRANCH_MANAGER,
            'business_id' => $business->id,
            'branch_id' => $branch->id,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'status' => 'active',
        ]);
        AuditLog::record($this->owner(), 'manager.created', $member, ['branch_id' => $branch->id]);

        return redirect()->route('biz.managers')->with('status', $member->fullName().', "'.$branch->name.'" şubesinin yöneticisi olarak eklendi. '.$member->email.' ve belirlediğiniz şifreyle giriş yapabilir.');
    }

    public function update(Request $request, int $manager): RedirectResponse
    {
        $member = $this->findManager($request, $manager);
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $data = $request->validate($this->rules($member) + [
            'password' => ['nullable', 'string', 'confirmed', Password::min(10)],
        ], [], $this->attributes());

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
        AuditLog::record($this->owner(), 'manager.updated', $member, ['password_changed' => ! empty($data['password'])]);

        return redirect()->route('biz.managers')->with('status', $member->fullName().' güncellendi.');
    }

    public function toggle(Request $request, int $manager): RedirectResponse
    {
        $member = $this->findManager($request, $manager);

        if ($member->status === 'active') {
            $member->update(['status' => 'passive']);
            $member->tokens()->delete();
            AuditLog::record($this->owner(), 'manager.status_changed', $member, ['to' => 'passive']);

            return back()->with('status', $member->fullName().' pasifleştirildi. "'.$member->branch?->name.'" şubesine yeni yönetici ekleyebilirsiniz.');
        }

        // Yeniden aktif etme: şubede başka aktif yönetici olmamalı
        $other = PanelUser::query()->where('branch_id', $member->branch_id)->where('role', PanelUser::ROLE_BRANCH_MANAGER)
            ->where('status', 'active')->where('id', '<>', $member->id)->exists();
        if ($other) {
            return back()->with('error', '"'.$member->branch?->name.'" şubesinin şu an başka bir aktif yöneticisi var. Önce onu pasifleştirin.');
        }
        if ($member->branch?->status === 'deleted') {
            return back()->with('error', 'Silinmiş şubenin yöneticisi aktif edilemez.');
        }

        $member->update(['status' => 'active']);
        AuditLog::record($this->owner(), 'manager.status_changed', $member, ['to' => 'active']);

        return back()->with('status', $member->fullName().' yeniden aktif.');
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
        ];
    }

    private function attributes(): array
    {
        return ['first_name' => 'ad', 'last_name' => 'soyad', 'email' => 'e-posta', 'phone' => 'telefon', 'password' => 'şifre'];
    }
}
