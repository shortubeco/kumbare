<?php

namespace App\Domain\Reviews;

use App\Domain\DomainException;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\PanelUser;
use App\Models\Review;
use App\Models\ReviewReport;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Puan (1–10 tam sayı) ve yorum. Müşteri yalnızca ödül kazandığı
 * (o şubede yemek yediği) şubeyi değerlendirebilir; şube başına tek değerlendirme.
 */
class ReviewService
{
    public function __construct(private readonly RatingAggregator $aggregator) {}

    public function canReview(Customer $customer, Branch $branch): bool
    {
        return WalletTransaction::query()
            ->where('branch_id', $branch->id)
            ->where('customer_id', $customer->id)
            ->where('type', WalletTransaction::TYPE_EARN)
            ->exists();
    }

    public function upsert(Customer $customer, Branch $branch, int $score, ?string $comment): Review
    {
        if (! $this->canReview($customer, $branch)) {
            throw new DomainException(
                'Yalnızca ödül kazandığınız restoranları değerlendirebilirsiniz.',
                'review_not_allowed',
                403
            );
        }

        return DB::transaction(function () use ($customer, $branch, $score, $comment) {
            $review = Review::query()
                ->where('branch_id', $branch->id)
                ->where('customer_id', $customer->id)
                ->lockForUpdate()
                ->first();

            if ($review && $review->status === 'removed') {
                throw new DomainException(
                    'Değerlendirmeniz platform kurallarına aykırı bulunduğu için kaldırıldı.',
                    'review_removed',
                    403
                );
            }

            $review ??= new Review(['branch_id' => $branch->id, 'customer_id' => $customer->id]);
            $review->score = $score;
            $review->comment = $comment;
            $review->status = 'published';
            $review->save();

            $this->aggregator->refresh($branch);

            return $review;
        });
    }

    public function report(Customer $customer, Review $review, string $reason): ReviewReport
    {
        if ($review->customer_id === $customer->id) {
            throw new DomainException('Kendi yorumunuzu şikâyet edemezsiniz.', 'invalid_report');
        }

        return ReviewReport::query()->firstOrCreate(
            [
                'review_id' => $review->id,
                'reporter_type' => 'customer',
                'reporter_id' => $customer->id,
                'status' => 'open',
            ],
            ['reason' => $reason]
        );
    }

    /** Platform yöneticisi yorumu kaldırır; açık şikâyetler çözülmüş sayılır, puan yeniden hesaplanır. */
    public function remove(PanelUser $admin, Review $review, string $reason): void
    {
        DB::transaction(function () use ($admin, $review, $reason) {
            $review = Review::query()->lockForUpdate()->findOrFail($review->id);

            if ($review->status !== 'removed') {
                $review->update([
                    'status' => 'removed',
                    'removed_by' => $admin->id,
                    'removed_reason' => $reason,
                    'removed_at' => now(),
                ]);
                $this->aggregator->refresh($review->branch);
            }

            $this->resolveReports($admin, $review);
            AuditLog::record($admin, 'review.removed', $review, ['reason' => $reason]);
        });
    }

    /** Şikâyetler yersiz bulundu: yorum yayında kalır, açık şikâyetler kapanır. */
    public function dismissReports(PanelUser $admin, Review $review): void
    {
        DB::transaction(function () use ($admin, $review) {
            $this->resolveReports($admin, $review);
            AuditLog::record($admin, 'review.reports_dismissed', $review);
        });
    }

    private function resolveReports(PanelUser $admin, Review $review): void
    {
        ReviewReport::query()
            ->where('review_id', $review->id)
            ->where('status', 'open')
            ->update(['status' => 'resolved', 'resolved_by' => $admin->id, 'resolved_at' => now(), 'updated_at' => now()]);
    }
}
