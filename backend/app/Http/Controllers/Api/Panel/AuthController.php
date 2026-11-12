<?php

namespace App\Http\Controllers\Api\Panel;

use App\Domain\Counter\MonthlyCounterService;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PanelUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = PanelUser::query()->whereRaw('lower(email) = ?', [mb_strtolower($data['email'])])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            AuditLog::record(null, 'panel.login_failed', null, ['email' => mb_strtolower($data['email'])]);
            throw ValidationException::withMessages(['email' => 'E-posta adresi veya şifre hatalı.']);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages(['email' => 'Hesabınız pasif durumda.']);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        AuditLog::record($user, 'panel.login', $user);

        return response()->json([
            'token' => $user->createToken($data['device_name'] ?? 'panel')->plainTextToken,
            'user' => $this->userPayload($user->load('permissions')),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Çıkış yapıldı.']);
    }

    public function me(Request $request, MonthlyCounterService $counter): JsonResponse
    {
        /** @var PanelUser $user */
        $user = $request->user();
        $payload = $this->userPayload($user);

        if ($user->worksAtBranch() && $user->branch) {
            $payload['monthly_counter'] = $counter->status($user->branch);
        }

        return response()->json(['data' => $payload]);
    }

    private function userPayload(PanelUser $user): array
    {
        $branch = $user->branch?->loadMissing('business');

        return [
            'id' => $user->id,
            'role' => $user->role,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'branch' => $branch ? [
                'id' => $branch->id,
                'name' => $branch->name,
                'display_name' => $branch->business->name.' – '.$branch->name,
                'reward_rate' => (string) $branch->reward_rate,
            ] : null,
            'permissions' => $user->isBranchManager()
                ? array_merge(PanelUser::STAFF_PERMISSIONS, ['correction'])
                : $user->permissions->pluck('permission')->values()->all(),
        ];
    }
}
