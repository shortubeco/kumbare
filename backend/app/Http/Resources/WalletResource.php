<?php

namespace App\Http\Resources;

use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Wallet $w */
        $w = $this->resource;
        $branch = $w->branch;

        return [
            'id' => $w->id,
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'display_name' => $branch->business->name.' – '.$branch->name,
                'logo_url' => Support::fileUrl($branch->business->logo_path),
                'reward_rate' => (string) $branch->reward_rate,
                'reward_terms' => $branch->reward_terms,
            ],
            'balance' => (string) $w->balance,
            'total_earned' => (string) $w->total_earned,
            'total_redeemed' => (string) $w->total_redeemed,
        ];
    }
}
