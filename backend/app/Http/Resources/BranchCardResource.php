<?php

namespace App\Http\Resources;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Listelerde kullanılan restoran (şube) kartı. */
class BranchCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Branch $b */
        $b = $this->resource;
        $business = $b->business;

        return [
            'id' => $b->id,
            'name' => $b->name,
            'display_name' => $business->name.' – '.$b->name,
            'slug' => $b->slug,
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'slug' => $business->slug,
                'logo_url' => Support::fileUrl($business->logo_path),
            ],
            'category' => $business->category ? [
                'id' => $business->category->id,
                'name' => $business->category->name,
            ] : null,
            'location' => [
                'city' => $b->district?->city?->name,
                'district' => $b->district?->displayName(),
                'latitude' => $b->latitude,
                'longitude' => $b->longitude,
                'distance_km' => isset($b->distance_km) ? round((float) $b->distance_km, 1) : null,
            ],
            'reward_rate' => (string) $b->reward_rate,
            'reward_label' => Support::rewardLabel((string) $b->reward_rate),
            'rating' => Support::rating($b->rating_avg !== null ? (string) $b->rating_avg : null, (int) $b->rating_count),
            'is_sponsored' => (bool) ($b->is_sponsored ?? false),
            'has_campaign' => (bool) ($b->has_campaign ?? false),
        ];
    }
}
