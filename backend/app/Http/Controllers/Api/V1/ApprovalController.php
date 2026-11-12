<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Loyalty\ApprovalService;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApprovalRequestResource;
use App\Models\ApprovalRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Bildirimler ekranı: onay bekleyen ödül kullanım ve düzeltme istekleri.
 * Mobil uygulama bu listeyi yalnızca açılışta ve Yenile butonunda çeker.
 */
class ApprovalController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $requests = ApprovalRequest::query()
            ->where('customer_id', $request->user()->id)
            ->where('status', ApprovalRequest::STATUS_PENDING)
            ->with(['branch.business', 'wallet', 'requester', 'relatedTransaction'])
            ->orderBy('created_at')
            ->get();

        return ApprovalRequestResource::collection($requests);
    }

    public function approve(Request $request, int $approvalRequest, ApprovalService $service): JsonResponse
    {
        $result = $service->approve($request->user(), $approvalRequest);
        $model = $result['request']->load(['branch.business', 'wallet', 'requester', 'relatedTransaction']);

        return response()->json([
            'message' => $result['message'],
            'approved' => $result['approved'],
            'data' => new ApprovalRequestResource($model),
        ], $result['approved'] ? 200 : 422);
    }

    public function reject(Request $request, int $approvalRequest, ApprovalService $service): JsonResponse
    {
        $model = $service->reject($request->user(), $approvalRequest)
            ->load(['branch.business', 'wallet', 'requester', 'relatedTransaction']);

        return response()->json([
            'message' => 'İstek reddedildi.',
            'data' => new ApprovalRequestResource($model),
        ]);
    }
}
