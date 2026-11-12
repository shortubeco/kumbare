<?php

namespace App\Domain\Billing;

use App\Domain\DomainException;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\PanelUser;
use App\Models\PaymentNotification;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Sponsorship;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Şube yöneticisinin havale bildirimi (Profesyonel paket veya sponsorluk).
 * Bildirim platform yöneticisinin onayına düşer (bkz. Platform\PaymentReviewService).
 */
class PaymentSubmissionService
{
    /** Sponsorluk en fazla kaç ay ileriye bildirilebilir (içinde bulunulan ay dahil). */
    public const SPONSOR_MONTHS_AHEAD = 6;

    /** Beklenen tutar (ayarlardan); fiyat belirlenmemişse null. */
    public function expectedAmount(string $purpose, ?string $cycle): ?string
    {
        if ($purpose === 'sponsorship') {
            $price = Setting::get('sponsorship_monthly_price');

            return $price !== null && (float) $price > 0 ? number_format((float) $price, 2, '.', '') : null;
        }

        $pro = Plan::query()->where('code', 'pro')->first();
        $price = $cycle === 'yearly' ? $pro?->yearly_price : $pro?->monthly_price;

        return $price !== null && (float) $price > 0 ? (string) $price : null;
    }

    /** Sponsorluk için seçilebilecek aylar: [ 'Y-m-01' => durum ] (available | sponsored | pending) */
    public function sponsorshipMonths(Branch $branch): array
    {
        $first = CarbonImmutable::now()->startOfMonth();
        $months = [];
        for ($i = 0; $i < self::SPONSOR_MONTHS_AHEAD; $i++) {
            $months[$first->addMonthsNoOverflow($i)->toDateString()] = 'available';
        }

        $sponsored = Sponsorship::query()->where('branch_id', $branch->id)
            ->whereIn('status', ['pending', 'active'])->whereIn('month', array_keys($months))
            ->pluck('month')->map(fn ($m) => CarbonImmutable::parse($m)->toDateString());
        $pending = PaymentNotification::query()->where('branch_id', $branch->id)
            ->where('purpose', 'sponsorship')->where('status', 'pending')->whereIn('sponsorship_month', array_keys($months))
            ->pluck('sponsorship_month')->map(fn ($m) => CarbonImmutable::parse($m)->toDateString());

        foreach ($pending as $m) { $months[$m] = 'pending'; }
        foreach ($sponsored as $m) { $months[$m] = 'sponsored'; }

        return $months;
    }

    /**
     * @param  array{purpose:string, billing_cycle?:?string, sponsorship_month?:?string, amount:string,
     *               sender_name:string, transfer_date:string, description?:?string}  $data
     */
    public function submit(PanelUser $manager, Branch $branch, array $data, ?UploadedFile $receipt = null): PaymentNotification
    {
        $path = null;

        try {
            return DB::transaction(function () use ($manager, $branch, $data, $receipt, &$path) {
                // Aynı şubeden eşzamanlı iki bildirim çakışmasın
                Branch::query()->whereKey($branch->id)->lockForUpdate()->first();

                if ($data['purpose'] === 'subscription') {
                    $hasPending = PaymentNotification::query()->where('branch_id', $branch->id)
                        ->where('purpose', 'subscription')->where('status', 'pending')->exists();
                    if ($hasPending) {
                        throw new DomainException('Onay bekleyen bir paket bildiriminiz zaten var. Sonuçlanınca yenisini gönderebilirsiniz.', 'pending_exists');
                    }
                } else {
                    $state = $this->sponsorshipMonths($branch)[$data['sponsorship_month']] ?? null;
                    if ($state === null) {
                        throw new DomainException('Bu ay için sponsorluk bildirimi yapılamaz.', 'invalid_month');
                    }
                    if ($state === 'sponsored') {
                        throw new DomainException('Şubeniz bu ay zaten sponsorlu.', 'already_sponsored');
                    }
                    if ($state === 'pending') {
                        throw new DomainException('Bu ay için onay bekleyen bir sponsorluk bildiriminiz var.', 'pending_exists');
                    }
                }

                if ($receipt) {
                    $ext = strtolower($receipt->getClientOriginalExtension() ?: $receipt->extension() ?: 'bin');
                    $path = $receipt->storeAs('dekontlar/'.$branch->id, Str::lower(Str::random(32)).'.'.$ext, 'local');
                }

                $payment = PaymentNotification::query()->create([
                    'branch_id' => $branch->id,
                    'submitted_by' => $manager->id,
                    'purpose' => $data['purpose'],
                    'billing_cycle' => $data['purpose'] === 'subscription' ? $data['billing_cycle'] : null,
                    'sponsorship_month' => $data['purpose'] === 'sponsorship' ? $data['sponsorship_month'] : null,
                    'amount' => $data['amount'],
                    'sender_name' => $data['sender_name'],
                    'transfer_date' => $data['transfer_date'],
                    'description' => $data['description'] ?? null,
                    'receipt_path' => $path ?: null,
                    'status' => 'pending',
                ]);

                AuditLog::record($manager, 'payment.submitted', $payment, [
                    'purpose' => $payment->purpose,
                    'amount' => (string) $payment->amount,
                    'expected' => $this->expectedAmount($payment->purpose, $payment->billing_cycle),
                ]);

                return $payment;
            });
        } catch (\Throwable $e) {
            // İşlem geri alındıysa yüklenen dosya da silinir
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $e;
        }
    }
}
