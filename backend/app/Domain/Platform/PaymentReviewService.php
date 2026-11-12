<?php

namespace App\Domain\Platform;

use App\Domain\DomainException;
use App\Domain\Notifications\PanelNotifier;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\PanelUser;
use App\Models\PaymentNotification;
use App\Models\Sponsorship;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Havale bildirimlerinin platform yöneticisi tarafından onaylanması / reddedilmesi.
 * Onayda abonelik (Profesyonel paket) veya sponsorluk kaydı otomatik oluşturulur.
 */
class PaymentReviewService
{
    public function __construct(private readonly PanelNotifier $notifier) {}

    /** @return Subscription|Sponsorship */
    public function approve(PanelUser $admin, PaymentNotification $payment, ?string $note = null): Subscription|Sponsorship
    {
        return DB::transaction(function () use ($admin, $payment, $note) {
            $payment = PaymentNotification::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status !== 'pending') {
                throw new DomainException('Bu bildirim daha önce sonuçlandırılmış.', 'already_reviewed', 422);
            }

            $result = $payment->purpose === 'subscription'
                ? $this->createSubscription($admin, $payment)
                : $this->createSponsorship($payment);

            $payment->update([
                'status' => 'approved',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);

            AuditLog::record($admin, 'payment.approved', $payment, [
                'purpose' => $payment->purpose,
                'amount' => (string) $payment->amount,
                'created' => class_basename($result).'#'.$result->id,
            ]);

            $this->notifier->notifyBranchManager($payment->branch, 'payment_approved', array_filter([
                'payment_notification_id' => $payment->id,
                'purpose' => $payment->purpose,
                'subscription_id' => $result instanceof Subscription ? $result->id : null,
                'ends_at' => $result instanceof Subscription ? $result->ends_at->toIso8601String() : null,
                'sponsorship_month' => $result instanceof Sponsorship ? $result->month->toDateString() : null,
            ]));

            return $result;
        });
    }

    public function reject(PanelUser $admin, PaymentNotification $payment, string $note): void
    {
        DB::transaction(function () use ($admin, $payment, $note) {
            $payment = PaymentNotification::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status !== 'pending') {
                throw new DomainException('Bu bildirim daha önce sonuçlandırılmış.', 'already_reviewed', 422);
            }

            $payment->update([
                'status' => 'rejected',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);

            AuditLog::record($admin, 'payment.rejected', $payment, ['note' => $note]);

            $this->notifier->notifyBranchManager($payment->branch, 'payment_rejected', [
                'payment_notification_id' => $payment->id,
                'purpose' => $payment->purpose,
                'note' => $note,
            ]);
        });
    }

    /**
     * Yeni dönem, şubenin süresi devam eden Profesyonel paketinin bitişinden başlar
     * (erken yenilemede kalan gün kaybolmaz); yoksa hemen başlar.
     */
    private function createSubscription(PanelUser $admin, PaymentNotification $payment): Subscription
    {
        $now = CarbonImmutable::now();

        // Aynı şubeye eşzamanlı iki onay dönemleri üst üste bindirmesin.
        Branch::query()->whereKey($payment->branch_id)->lockForUpdate()->first();

        $currentEnd = Subscription::query()
            ->where('branch_id', $payment->branch_id)
            ->where('status', 'active')
            ->where('ends_at', '>', $now)
            ->max('ends_at');

        $start = $currentEnd ? CarbonImmutable::parse($currentEnd) : $now;
        $end = $payment->billing_cycle === 'yearly' ? $start->addYearNoOverflow() : $start->addMonthNoOverflow();

        return Subscription::query()->create([
            'branch_id' => $payment->branch_id,
            'billing_cycle' => $payment->billing_cycle,
            'starts_at' => $start,
            'ends_at' => $end,
            'price' => $payment->amount,
            'status' => 'active',
            'payment_notification_id' => $payment->id,
            'activated_by' => $admin->id,
        ]);
    }

    private function createSponsorship(PaymentNotification $payment): Sponsorship
    {
        $month = CarbonImmutable::parse($payment->sponsorship_month)->startOfMonth();
        $currentMonth = CarbonImmutable::now()->startOfMonth();

        if ($month->lt($currentMonth)) {
            throw new DomainException('Bildirimdeki sponsorluk ayı geçmişte kaldı; onaylanamaz. Bildirimi gerekçesiyle reddedin.', 'past_month', 422);
        }

        $existing = Sponsorship::query()
            ->where('branch_id', $payment->branch_id)
            ->where('month', $month->toDateString())
            ->lockForUpdate()
            ->first();

        if ($existing && in_array($existing->status, ['pending', 'active'], true)) {
            throw new DomainException('Bu şubenin o ay için zaten bir sponsorluğu var.', 'sponsorship_exists', 422);
        }

        $attrs = [
            'status' => $month->equalTo($currentMonth) ? 'active' : 'pending',
            'payment_notification_id' => $payment->id,
        ];

        if ($existing) {
            $existing->update($attrs);

            return $existing;
        }

        return Sponsorship::query()->create(['branch_id' => $payment->branch_id, 'month' => $month->toDateString()] + $attrs);
    }
}
