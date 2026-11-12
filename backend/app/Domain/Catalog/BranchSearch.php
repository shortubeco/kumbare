<?php

namespace App\Domain\Catalog;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Builder;

/** Restoran (şube) listeleme: filtreler, sıralama, mesafe ve sponsorlu bilgisi. */
class BranchSearch
{
    public const SORTS = ['rate_desc', 'rate_asc', 'popular', 'newest', 'score', 'most_reviewed', 'nearest'];

    /** En yüksek puan sıralamasına girmek için gereken en az değerlendirme sayısı. */
    public const MIN_REVIEWS_FOR_SCORE_RANKING = 10;

    public function query(array $filters = [], ?string $sort = null): Builder
    {
        $query = Branch::query()
            ->publiclyVisible()
            ->select('branches.*')
            ->with(['business.category', 'district.city']);

        $this->withSponsoredFlag($query);
        $this->withCampaignFlag($query);

        $lat = $filters['lat'] ?? null;
        $lng = $filters['lng'] ?? null;
        $hasLocation = is_numeric($lat) && is_numeric($lng);

        if ($hasLocation) {
            $query->selectRaw(
                '(6371 * acos(least(1, greatest(-1,
                    cos(radians(?)) * cos(radians(branches.latitude)) * cos(radians(branches.longitude) - radians(?))
                    + sin(radians(?)) * sin(radians(branches.latitude))
                )))) AS distance_km',
                [(float) $lat, (float) $lng, (float) $lat]
            );

            if (! empty($filters['radius_km'])) {
                $query->whereNotNull('branches.latitude')
                    ->whereRaw(
                        '(6371 * acos(least(1, greatest(-1,
                            cos(radians(?)) * cos(radians(branches.latitude)) * cos(radians(branches.longitude) - radians(?))
                            + sin(radians(?)) * sin(radians(branches.latitude))
                        )))) <= ?',
                        [(float) $lat, (float) $lng, (float) $lat, (float) $filters['radius_km']]
                    );
            }
        }

        if (! empty($filters['city'])) {
            $query->where('branches.city_id', (int) $filters['city']);
        }
        if (! empty($filters['district'])) {
            $query->where('branches.district_id', (int) $filters['district']);
        }
        if (! empty($filters['category'])) {
            $query->whereHas('business', fn (Builder $q) => $q->where('category_id', (int) $filters['category']));
        }
        if (isset($filters['min_rate']) && is_numeric($filters['min_rate'])) {
            $query->where('branches.reward_rate', '>=', $filters['min_rate']);
        }
        if (isset($filters['min_score']) && is_numeric($filters['min_score'])) {
            $query->where('branches.rating_avg', '>=', $filters['min_score']);
        }
        if (! empty($filters['has_campaign'])) {
            $query->whereExists(function ($q) {
                $q->selectRaw('1')->from('campaigns')
                    ->whereColumn('campaigns.branch_id', 'branches.id')
                    ->where('campaigns.status', 'active')
                    ->where('campaigns.starts_at', '<=', now())
                    ->where('campaigns.ends_at', '>', now());
            });
        }
        if (! empty($filters['new'])) {
            $query->where('branches.created_at', '>=', now()->subDays(30));
        }
        if (! empty($filters['q'])) {
            $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], mb_strtolower(trim($filters['q']))).'%';
            $query->where(function (Builder $q) use ($term) {
                $q->whereRaw('lower(branches.name) LIKE ?', [$term])
                    ->orWhereHas('business', fn (Builder $b) => $b->whereRaw('lower(name) LIKE ?', [$term]));
            });
        }

        $this->applySort($query, $sort, $hasLocation);

        return $query;
    }

    private function applySort(Builder $query, ?string $sort, bool $hasLocation): void
    {
        $sort = in_array($sort, self::SORTS, true) ? $sort : 'popular';

        match ($sort) {
            'rate_desc' => $query->orderByDesc('branches.reward_rate'),
            'rate_asc' => $query->orderBy('branches.reward_rate'),
            'newest' => $query->orderByDesc('branches.created_at'),
            // 10'dan az değerlendirmesi olanlar sıralamaya girmez, listenin sonunda kalır.
            'score' => $query->orderByRaw(
                '(branches.rating_count >= ?) DESC, branches.rating_avg DESC NULLS LAST, branches.rating_count DESC',
                [self::MIN_REVIEWS_FOR_SCORE_RANKING]
            ),
            'most_reviewed' => $query->orderByDesc('branches.rating_count'),
            'nearest' => $hasLocation
                ? $query->whereNotNull('branches.latitude')->orderBy('distance_km')
                : $query->orderByDesc('branches.popularity_score'),
            default => $query->orderByDesc('branches.popularity_score'),
        };

        $query->orderBy('branches.id');
    }

    private function withSponsoredFlag(Builder $query): void
    {
        // Sponsorluk, ödemesi onaylanıp oluşturulduysa (pending/active) ilgili ay boyunca geçerlidir.
        $query->selectRaw(
            "EXISTS (SELECT 1 FROM sponsorships s WHERE s.branch_id = branches.id
                     AND s.month = ? AND s.status IN ('pending', 'active')) AS is_sponsored",
            [now()->startOfMonth()->toDateString()]
        );
    }

    private function withCampaignFlag(Builder $query): void
    {
        $now = now();
        $query->selectRaw(
            "EXISTS (SELECT 1 FROM campaigns c WHERE c.branch_id = branches.id
                     AND c.status = 'active' AND c.starts_at <= ? AND c.ends_at > ?) AS has_campaign",
            [$now, $now]
        );
    }

    /** Sponsorlu şubeleri en üste alan sorgu (ana sayfa bloğu). */
    public function sponsored(): Builder
    {
        return $this->query()->whereExists(function ($q) {
            $q->selectRaw('1')->from('sponsorships')
                ->whereColumn('sponsorships.branch_id', 'branches.id')
                ->where('sponsorships.month', now()->startOfMonth()->toDateString())
                ->whereIn('sponsorships.status', ['pending', 'active']);
        });
    }
}
