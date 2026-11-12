<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Customers\CustomerRegistrar;
use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request, CustomerRegistrar $registrar): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^[0-9+ ]{10,20}$/', Rule::unique('customers', 'phone')],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'district_id' => ['required', 'integer', Rule::exists('districts', 'id')->where('city_id', $request->input('city_id'))],
            'birth_date' => ['required', 'date_format:Y-m-d', 'before:today', 'after:1900-01-01'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)],
            'kvkk_consent' => ['accepted'],
        ]);

        $data['phone'] = preg_replace('/\s+/', '', $data['phone']);

        if (Customer::withTrashed()->whereRaw('lower(email) = ?', [mb_strtolower($data['email'])])->exists()) {
            throw ValidationException::withMessages(['email' => 'Bu e-posta adresi zaten kayıtlı.']);
        }
        if (Customer::withTrashed()->where('phone', $data['phone'])->exists()) {
            throw ValidationException::withMessages(['phone' => 'Bu telefon numarası zaten kayıtlı.']);
        }

        $customer = $registrar->register($data);
        $token = $customer->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'customer' => new CustomerResource($customer->load('city', 'district.city')),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $customer = Customer::query()->whereRaw('lower(email) = ?', [mb_strtolower($data['email'])])->first();

        if (! $customer || ! Hash::check($data['password'], $customer->password)) {
            throw ValidationException::withMessages(['email' => 'E-posta adresi veya şifre hatalı.']);
        }

        if ($customer->status !== 'active') {
            throw ValidationException::withMessages(['email' => 'Hesabınız pasif durumda.']);
        }

        $token = $customer->createToken($data['device_name'] ?? 'mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'customer' => new CustomerResource($customer->load('city', 'district.city')),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Çıkış yapıldı.']);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email']]);

        // Hesabın var olup olmadığı açığa çıkarılmaz.
        Password::broker('customers')->sendResetLink(['email' => mb_strtolower($data['email'])]);

        return response()->json([
            'message' => 'Bu e-posta adresi kayıtlıysa şifre sıfırlama bağlantısı gönderildi.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::broker('customers')->reset(
            [
                'email' => mb_strtolower($data['email']),
                'token' => $data['token'],
                'password' => $data['password'],
                'password_confirmation' => $request->input('password_confirmation'),
            ],
            function (Customer $customer, string $password) {
                $customer->password = $password;
                $customer->save();
                $customer->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['token' => 'Şifre sıfırlama bağlantısı geçersiz veya süresi dolmuş.']);
        }

        return response()->json(['message' => 'Şifreniz güncellendi. Yeni şifrenizle giriş yapabilirsiniz.']);
    }
}
