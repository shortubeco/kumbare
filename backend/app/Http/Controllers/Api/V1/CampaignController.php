<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CampaignResource;
use App\Models\Campaign;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Kampanyalar ekranı: müşterinin kayıtlı ilçesindeki şubelerin aktif kampanyaları.
 * Mobil uygulama bu listeyi yalnızca açılışta ve Yenile butonunda çeker.
 */
class CampaignController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user()->load('district.city');

        $campaigns = Campaign::query()
            ->running()
            ->whereHas('branch', fn (Builder $q) => $q->publiclyVisible()->where('district_id', $customer->district_id))
            ->with(['branch.business.category', 'branch.district.city'])
            ->orderByDesc('starts_at')
            ->get();

        return response()->json([
            'district' => [
                'id' => $customer->district_id,
                'name' => $customer->district?->displayName(),
                'city' => $customer->district?->city?->name,
            ],
            'data' => CampaignResource::collection($campaigns)->toArray($request),
        ]);
    }
}
