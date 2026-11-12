<?php

namespace App\Http\Resources;

use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignResource extends JsonResource
{
    private const DAY_NAMES = [1 => 'Pazartesi', 2 => 'Salı', 3 => 'Çarşamba', 4 => 'Perşembe', 5 => 'Cuma', 6 => 'Cumartesi', 7 => 'Pazar'];

    public function toArray(Request $request): array
    {
        /** @var Campaign $c */
        $c = $this->resource;
        $days = $c->weekdayList();

        $conditions = [];
        if ($days) {
            $conditions[] = 'Geçerli günler: '.implode(', ', array_map(fn ($d) => self::DAY_NAMES[$d] ?? $d, $days));
        }
        if ($c->new_customers_only) {
            $conditions[] = 'Yalnızca yeni müşteriler';
        }
        if ($c->birthday_only) {
            $conditions[] = 'Yalnızca doğum gününüzde';
        }

        return [
            'id' => $c->id,
            'title' => $c->title,
            'description' => $c->description,
            'image_url' => Support::fileUrl($c->image_path),
            'terms' => $c->terms,
            'type' => $c->type,
            'reward_rate' => $c->reward_rate !== null ? (string) $c->reward_rate : null,
            'weekdays' => $days,
            'new_customers_only' => $c->new_customers_only,
            'birthday_only' => $c->birthday_only,
            'conditions' => $conditions,
            'starts_at' => Support::iso($c->starts_at),
            'ends_at' => Support::iso($c->ends_at),
            'branch' => $this->whenLoaded('branch', fn () => new BranchCardResource($c->branch)),
        ];
    }
}
