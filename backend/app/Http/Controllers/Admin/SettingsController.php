<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Paketler (açıklama, Profesyonel fiyatları) ve platform ayarları. */
class SettingsController extends AdminController
{
    public const KEYS = [
        'free_monthly_customer_limit', 'warning_threshold_1', 'warning_threshold_2',
        'subscription_expiry_warning_days', 'bank_name', 'iban', 'account_holder', 'sponsorship_monthly_price',
    ];

    public function edit(): View
    {
        return view('admin.settings', [
            'plans' => Plan::query()->orderByRaw("CASE code WHEN 'free' THEN 0 ELSE 1 END")->get()->keyBy('code'),
            'settings' => collect(self::KEYS)->mapWithKeys(fn ($k) => [$k => Setting::get($k)]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        // "1.234,50" veya "1234.50" biçimindeki tutarlar kabul edilir.
        $request->merge(collect(['pro_monthly_price', 'pro_yearly_price', 'sponsorship_monthly_price'])
            ->mapWithKeys(fn ($k) => [$k => $this->normalizeMoney($request->input($k))])->all());
        $request->merge(['iban' => strtoupper(preg_replace('/\s+/', '', (string) $request->input('iban')))]);

        $data = $request->validate([
            'free_description' => ['nullable', 'string', 'max:1000'],
            'pro_description' => ['nullable', 'string', 'max:1000'],
            'pro_monthly_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'pro_yearly_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'free_monthly_customer_limit' => ['required', 'integer', 'min:1', 'max:1000000'],
            'warning_threshold_1' => ['required', 'integer', 'min:1', 'max:99'],
            'warning_threshold_2' => ['required', 'integer', 'min:1', 'max:99', 'gt:warning_threshold_1'],
            'subscription_expiry_warning_days' => ['required', 'integer', 'min:1', 'max:60'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'iban' => ['nullable', 'string', 'regex:/^TR\d{24}$/'],
            'account_holder' => ['nullable', 'string', 'max:150'],
            'sponsorship_monthly_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ], [
            'iban.regex' => 'IBAN "TR" ile başlamalı ve 26 karakter olmalıdır.',
            'warning_threshold_2.gt' => 'İkinci uyarı eşiği birinciden büyük olmalıdır.',
        ], [
            'pro_monthly_price' => 'aylık fiyat',
            'pro_yearly_price' => 'yıllık fiyat',
            'free_monthly_customer_limit' => 'aylık müşteri limiti',
            'warning_threshold_1' => 'birinci uyarı eşiği',
            'warning_threshold_2' => 'ikinci uyarı eşiği',
            'subscription_expiry_warning_days' => 'abonelik uyarı gün sayısı',
            'bank_name' => 'banka adı',
            'account_holder' => 'alıcı adı',
            'sponsorship_monthly_price' => 'sponsorluk fiyatı',
        ]);

        $before = collect(self::KEYS)->mapWithKeys(fn ($k) => [$k => Setting::get($k)])->all();

        DB::transaction(function () use ($data, $before) {
            Plan::query()->where('code', 'free')->update(['description' => $data['free_description'], 'updated_at' => now()]);
            Plan::query()->where('code', 'pro')->update([
                'description' => $data['pro_description'],
                'monthly_price' => $data['pro_monthly_price'],
                'yearly_price' => $data['pro_yearly_price'],
                'updated_at' => now(),
            ]);

            $changes = [];
            foreach (self::KEYS as $key) {
                $value = isset($data[$key]) && $data[$key] !== '' ? (string) $data[$key] : '';
                if ((string) $before[$key] !== $value) {
                    $changes[$key] = ['from' => $before[$key], 'to' => $value];
                }
                Setting::put($key, $value);
            }

            AuditLog::record($this->admin(), 'settings.updated', null, [
                'settings' => $changes,
                'pro_monthly_price' => $data['pro_monthly_price'],
                'pro_yearly_price' => $data['pro_yearly_price'],
            ]);
        });

        return back()->with('status', 'Ayarlar kaydedildi. Yeni değerler hemen geçerli.');
    }

    private function normalizeMoney(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $value = str_replace([' ', 'TL', '₺'], '', $value);
        if (str_contains($value, ',')) {
            $value = str_replace(['.', ','], ['', '.'], $value);
        }

        return $value;
    }
}
