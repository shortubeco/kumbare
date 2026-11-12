<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Customers\CustomerRegistrar;
use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\ReviewResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class MeController extends Controller
{
    public function show(Request $request): CustomerResource
    {
        return new CustomerResource($request->user()->load('city', 'district.city'));
    }

    /** Doğum tarihi ve müşteri numarası değiştirilemez; gönderilse de dikkate alınmaz. */
    public function update(Request $request): CustomerResource
    {
        /** @var Customer $customer */
        $customer = $request->user();
        $cityId = $request->input('city_id', $customer->city_id);

        $data = $request->validate([
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'phone' => ['sometimes', 'required', 'string', 'regex:/^[0-9+ ]{10,20}$/'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255'],
            'city_id' => ['sometimes', 'required', 'integer', 'exists:cities,id'],
            // İl değiştiriliyorsa ilçe de zorunludur; ilçe her zaman (yeni veya mevcut) ile ait olmalıdır.
            'district_id' => [$request->has('city_id') ? 'required' : 'sometimes', 'integer', Rule::exists('districts', 'id')->where('city_id', $cityId)],
            'display_name_mode' => ['sometimes', 'required', Rule::in(['initials', 'full'])],
        ]);

        if (isset($data['phone'])) {
            $data['phone'] = preg_replace('/\s+/', '', $data['phone']);
            if (Customer::withTrashed()->where('phone', $data['phone'])->where('id', '!=', $customer->id)->exists()) {
                throw ValidationException::withMessages(['phone' => 'Bu telefon numarası başka bir hesapta kayıtlı.']);
            }
        }
        if (isset($data['email'])) {
            $data['email'] = mb_strtolower($data['email']);
            if (Customer::withTrashed()->whereRaw('lower(email) = ?', [$data['email']])->where('id', '!=', $customer->id)->exists()) {
                throw ValidationException::withMessages(['email' => 'Bu e-posta adresi başka bir hesapta kayıtlı.']);
            }
        }

        $customer->fill($data)->save();

        return new CustomerResource($customer->load('city', 'district.city'));
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)],
        ]);

        /** @var Customer $customer */
        $customer = $request->user();
        if (! Hash::check($data['current_password'], $customer->password)) {
            throw ValidationException::withMessages(['current_password' => 'Mevcut şifre hatalı.']);
        }

        $customer->password = $data['password'];
        $customer->save();

        // Bu cihaz dışındaki oturumlar kapatılır.
        $current = $customer->currentAccessToken();
        $customer->tokens()->when($current, fn ($q) => $q->where('id', '!=', $current->id))->delete();

        return response()->json(['message' => 'Şifreniz güncellendi.']);
    }

    public function destroy(Request $request, CustomerRegistrar $registrar): JsonResponse
    {
        $request->validate(['password' => ['required', 'string']]);

        /** @var Customer $customer */
        $customer = $request->user();
        if (! Hash::check($request->input('password'), $customer->password)) {
            throw ValidationException::withMessages(['password' => 'Şifre hatalı.']);
        }

        $registrar->delete($customer);

        return response()->json(['message' => 'Hesabınız silindi.']);
    }

    public function reviews(Request $request): AnonymousResourceCollection
    {
        $reviews = $request->user()->reviews()
            ->with(['branch.business', 'reply', 'customer'])
            ->latest('updated_at')
            ->paginate(20);

        return ReviewResource::collection($reviews);
    }
}
