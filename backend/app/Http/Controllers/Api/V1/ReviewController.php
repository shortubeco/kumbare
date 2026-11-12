<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\DomainException;
use App\Domain\Reviews\ReviewService;
use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Branch;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /** Puan (1–10 tam sayı) ve yorum ekler veya günceller. */
    public function upsert(Request $request, int $branch, ReviewService $service): JsonResponse
    {
        $data = $request->validate([
            'score' => ['required', 'integer', 'between:1,10'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $b = Branch::query()->publiclyVisible()->where('branches.id', $branch)->first();
        if (! $b) {
            throw DomainException::notFound('Restoran bulunamadı.');
        }

        $review = $service->upsert($request->user(), $b, (int) $data['score'], $data['comment'] ?? null);

        return response()->json([
            'message' => 'Değerlendirmeniz kaydedildi.',
            'data' => new ReviewResource($review->load('customer', 'reply')),
        ]);
    }

    public function report(Request $request, int $review, ReviewService $service): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $model = Review::query()->where('id', $review)->where('status', 'published')->first();
        if (! $model) {
            throw DomainException::notFound('Yorum bulunamadı.');
        }

        $service->report($request->user(), $model, $data['reason']);

        return response()->json(['message' => 'Şikâyetiniz alındı. İncelenecektir.'], 201);
    }
}
