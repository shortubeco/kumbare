<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Catalog\BranchSearch;
use App\Domain\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Resources\BranchCardResource;
use App\Models\Branch;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FavoriteController extends Controller
{
    public function index(Request $request, BranchSearch $search): AnonymousResourceCollection
    {
        $branches = $search->query([], 'newest')
            ->join('favorites', 'favorites.branch_id', '=', 'branches.id')
            ->where('favorites.customer_id', $request->user()->id)
            ->reorder('favorites.created_at', 'desc')
            ->get();

        return BranchCardResource::collection($branches);
    }

    public function store(Request $request, int $branch): JsonResponse
    {
        $b = $this->visibleBranch($branch);
        /** @var Customer $customer */
        $customer = $request->user();
        $customer->favoriteBranches()->syncWithoutDetaching([$b->id]);

        return response()->json(['message' => 'Favorilere eklendi.'], 201);
    }

    public function destroy(Request $request, int $branch): JsonResponse
    {
        $request->user()->favoriteBranches()->detach($branch);

        return response()->json(['message' => 'Favorilerden çıkarıldı.']);
    }

    private function visibleBranch(int $id): Branch
    {
        $branch = Branch::query()->publiclyVisible()->where('branches.id', $id)->first();
        if (! $branch) {
            throw DomainException::notFound('Restoran bulunamadı.');
        }

        return $branch;
    }
}
