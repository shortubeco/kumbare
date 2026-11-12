<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Catalog\BranchSearch;
use App\Http\Controllers\Controller;
use App\Http\Resources\BranchCardResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ana ekran blokları. Konum verilirse "yakındaki", verilmezse ilçe kullanılır. */
class HomeController extends Controller
{
    private const BLOCK_SIZE = 10;

    public function __invoke(Request $request, BranchSearch $search): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'district' => ['nullable', 'integer'],
        ]);

        $hasLocation = isset($data['lat'], $data['lng']);

        $nearby = match (true) {
            $hasLocation => $search->query(['lat' => $data['lat'], 'lng' => $data['lng'], 'radius_km' => 10], 'nearest'),
            ! empty($data['district']) => $search->query(['district' => $data['district']], 'popular'),
            default => null,
        };

        $blocks = [
            'sponsored' => $search->sponsored()->limit(self::BLOCK_SIZE)->get(),
            'nearby' => $nearby?->limit(self::BLOCK_SIZE)->get() ?? collect(),
            'top_rate' => $search->query([], 'rate_desc')->where('branches.reward_rate', '>', 0)->limit(self::BLOCK_SIZE)->get(),
            'with_campaign' => $search->query(['has_campaign' => true], 'popular')->limit(self::BLOCK_SIZE)->get(),
            'newest' => $search->query([], 'newest')->limit(self::BLOCK_SIZE)->get(),
        ];

        return response()->json(['data' => collect($blocks)->map(
            fn ($items) => BranchCardResource::collection($items)->toArray($request)
        )]);
    }
}
