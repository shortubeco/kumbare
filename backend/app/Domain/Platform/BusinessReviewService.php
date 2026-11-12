<?php

namespace App\Domain\Platform;

use App\Domain\DomainException;
use App\Domain\Notifications\PanelNotifier;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Business;
use App\Models\PanelUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * İşletme başvurularının onayı/reddi ve işletme/şube durum değişiklikleri.
 * Askıya alınan veya kapatılan işletmenin şubeleri sitede ve uygulamada görünmez,
 * personel işlem yapamaz (bkz. Branch::isOperational).
 */
class BusinessReviewService
{
    public const BUSINESS_STATUSES = [
        'pending' => 'Başvuru bekliyor',
        'approved' => 'Aktif',
        'rejected' => 'Reddedildi',
        'suspended' => 'Askıya alındı',
        'closed' => 'Kapatıldı',
    ];

    public const BRANCH_STATUSES = [
        'active' => 'Aktif',
        'passive' => 'Pasif',
        'deleted' => 'Silindi',
    ];

    public function __construct(private readonly PanelNotifier $notifier) {}

    public function approve(PanelUser $admin, Business $business): void
    {
        DB::transaction(function () use ($admin, $business) {
            $business = Business::query()->lockForUpdate()->findOrFail($business->id);
            if ($business->status !== 'pending') {
                throw new DomainException('Bu başvuru daha önce sonuçlandırılmış.', 'already_reviewed');
            }

            $business->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $admin->id,
                'rejection_reason' => null,
            ]);

            AuditLog::record($admin, 'business.approved', $business);
            $this->notifyOwner($business, 'business_approved', [],
                'Kumbare başvurunuz onaylandı',
                "Merhaba,\n\n\"{$business->name}\" için yaptığınız Kumbare başvurusu onaylandı. "
                ."Başvuruda belirlediğiniz e-posta ve şifreyle panele giriş yapabilirsiniz:\n\n"
                .route('panel.login')."\n\nKumbare");
        });
    }

    public function reject(PanelUser $admin, Business $business, string $reason): void
    {
        DB::transaction(function () use ($admin, $business, $reason) {
            $business = Business::query()->lockForUpdate()->findOrFail($business->id);
            if ($business->status !== 'pending') {
                throw new DomainException('Bu başvuru daha önce sonuçlandırılmış.', 'already_reviewed');
            }

            $business->update(['status' => 'rejected', 'rejection_reason' => $reason]);

            AuditLog::record($admin, 'business.rejected', $business, ['reason' => $reason]);
            $this->notifyOwner($business, 'business_rejected', ['reason' => $reason],
                'Kumbare başvurunuz hakkında',
                "Merhaba,\n\n\"{$business->name}\" için yaptığınız Kumbare başvurusu onaylanmadı.\n\n"
                ."Gerekçe: {$reason}\n\nSorularınız için bu e-postayı yanıtlayabilirsiniz.\n\nKumbare");
        });
    }

    /** Onaylı işletmeyi askıya alma, kapatma veya yeniden aktif etme. */
    public function setBusinessStatus(PanelUser $admin, Business $business, string $status, ?string $reason): void
    {
        if (! in_array($status, ['approved', 'suspended', 'closed'], true)) {
            throw new DomainException('Geçersiz durum.');
        }
        if (in_array($business->status, ['pending', 'rejected'], true)) {
            throw new DomainException('Başvurusu sonuçlanmamış veya reddedilmiş işletmenin durumu buradan değiştirilemez.');
        }
        if ($business->status === $status) {
            return;
        }

        $old = $business->status;
        $business->update(['status' => $status]);
        AuditLog::record($admin, 'business.status_changed', $business, ['from' => $old, 'to' => $status, 'reason' => $reason]);
        $this->notifyOwner($business, 'business_status_changed', ['status' => $status, 'reason' => $reason]);
    }

    /** Şubeyi aktif veya pasif yapma (silme işletme yöneticisine aittir). */
    public function setBranchStatus(PanelUser $admin, Branch $branch, string $status, ?string $reason): void
    {
        if (! in_array($status, ['active', 'passive'], true)) {
            throw new DomainException('Geçersiz durum.');
        }
        if ($branch->status === 'deleted') {
            throw new DomainException('Silinmiş şubenin durumu değiştirilemez.');
        }
        if ($branch->status === $status) {
            return;
        }

        $old = $branch->status;
        $branch->update(['status' => $status]);
        AuditLog::record($admin, 'branch.status_changed', $branch, ['from' => $old, 'to' => $status, 'reason' => $reason]);
        $this->notifier->notifyBranchManager($branch, 'branch_status_changed', ['status' => $status, 'reason' => $reason]);
    }

    private function notifyOwner(Business $business, string $type, array $data, ?string $mailSubject = null, ?string $mailBody = null): void
    {
        $owner = $business->owner()->first();
        if (! $owner) {
            return;
        }

        $this->notifier->notify($owner, $type, ['business_id' => $business->id] + $data);

        if ($mailSubject && $mailBody) {
            // E-posta gönderilemezse işlem geri alınmaz; hata loglanır.
            DB::afterCommit(function () use ($owner, $mailSubject, $mailBody) {
                try {
                    Mail::raw($mailBody, fn ($m) => $m->to($owner->email)->subject($mailSubject));
                } catch (Throwable $e) {
                    Log::warning('İşletme bildirim e-postası gönderilemedi', ['email' => $owner->email, 'error' => $e->getMessage()]);
                }
            });
        }
    }
}
