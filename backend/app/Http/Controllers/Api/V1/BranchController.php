<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Catalog\BranchSearch;
use App\Domain\DomainException;
use App\Domain\Reviews\RatingAggregator;
use App\Domain\Reviews\ReviewService;
use App\Http\Controllers\Controller;
use App\Http\Resources\BranchCardResource;
use App\Http\Resources\CampaignResource;
use App\Http\Resources\ReviewResource;
use App\Http\Resources\Support;
use App\Models\Branch;
use App\Models\BranchHour;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Review;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function index(Request $request, BranchSearch $search): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'city' => ['nullable', 'integer'],
            'district' => ['nullable', 'integer'],
            'category' => ['nullable', 'integer'],
            'min_rate' => ['nullable', 'numeric', 'between:0,100'],
            'min_score' => ['nullable', 'numeric', 'between:1,10'],
            'has_campaign' => ['nullable', 'boolean'],
            'new' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:100'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'radius_km' => ['nullable', 'numeric', 'between:0.1,500'],
            'sort' => ['nullable', Rule::in(BranchSearch::SORTS)],
            'per_page' => ['nullable', 'integer', 'between:1,50'],
        ]);

        $branches = $search->query($filters, $filters['sort'] ?? null)
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();

        return BranchCardResource::collection($branches);
    }

    public function show(Request $request, int $branch, BranchSearch $search, RatingAggregator $ratings, ReviewService $reviews): JsonResponse
    {
        /** @var Branch|null $b */
        $b = $search->query()->where('branches.id', $branch)->first();
        if (! $b) {
            throw DomainException::notFound('Restoran bulunamadı.');
        }

        $b->load(['hours', 'photos']);
        $business = $b->business;

        $campaigns = Campaign::query()->where('branch_id', $b->id)->running()->orderBy('ends_at')->get();

        $menu = MenuCategory::query()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')
            ->with(['items' => fn ($q) => $q->whereNull('deleted_at')])
            ->get()
            ->map(fn (MenuCategory $cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'items' => $cat->items->map(fn (MenuItem $i) => [
                    'id' => $i->id,
                    'name' => $i->name,
                    'description' => $i->description,
                    'price' => (string) $i->price,
                    'image_url' => Support::fileUrl($i->image_path),
                    'is_available' => $i->is_available,
                ])->values(),
            ])
            ->filter(fn ($cat) => $cat['items']->isNotEmpty())
            ->values();

        $otherBranches = Branch::query()
            ->publiclyVisible()
            ->where('business_id', $business->id)
            ->where('id', '!=', $b->id)
            ->with('district.city')
            ->orderBy('name')
            ->get()
            ->map(fn (Branch $o) => [
                'id' => $o->id,
                'name' => $o->name,
                'district' => $o->district?->displayName(),
                'reward_rate' => (string) $o->reward_rate,
            ]);

        $viewer = null;
        $customer = $request->user('sanctum');
        if ($customer instanceof Customer) {
            $wallet = Wallet::query()->where('branch_id', $b->id)->where('customer_id', $customer->id)->first();
            $myReview = Review::query()->where('branch_id', $b->id)->where('customer_id', $customer->id)->with('reply', 'customer')->first();
            $viewer = [
                'is_favorite' => $customer->favoriteBranches()->where('branches.id', $b->id)->exists(),
                'wallet' => $wallet ? ['id' => $wallet->id, 'balance' => (string) $wallet->balance] : null,
                'can_review' => $reviews->canReview($customer, $b),
                'my_review' => $myReview ? new ReviewResource($myReview) : null,
            ];
        }

        $card = (new BranchCardResource($b))->toArray($request);

        return response()->json(['data' => array_merge($card, [
            'description' => $b->description,
            'business_description' => $business->description,
            'address' => $b->address,
            'phone' => $b->phone,
            'email' => $b->email,
            'reward_terms' => $b->reward_terms,
            'hours' => $b->hours->map(fn (BranchHour $h) => [
                'weekday' => $h->weekday,
                'is_closed' => $h->is_closed,
                'opens_at' => $h->opens_at ? substr($h->opens_at, 0, 5) : null,
                'closes_at' => $h->closes_at ? substr($h->closes_at, 0, 5) : null,
            ]),
            'photos' => $b->photos->map(fn ($p) => Support::fileUrl($p->path)),
            'campaigns' => CampaignResource::collection($campaigns),
            'menu' => $menu,
            'other_branches' => $otherBranches,
            'rating_distribution' => $ratings->distribution($b),
            'viewer' => $viewer,
        ])]);
    }

    public function reviews(Request $request, int $branch): AnonymousResourceCollection
    {
        $data = $request->validate([
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'score'])],
        ]);

        $exists = Branch::query()->publiclyVisible()->where('branches.id', $branch)->exists();
        if (! $exists) {
            throw DomainException::notFound('Restoran bulunamadı.');
        }

        $query = Review::query()
            ->where('branch_id', $branch)
            ->where('status', 'published')
            ->with(['customer', 'reply']);

        match ($data['sort'] ?? 'newest') {
            'oldest' => $query->orderBy('created_at'),
            'score' => $query->orderByDesc('score')->orderByDesc('created_at'),
            default => $query->orderByDesc('created_at'),
        };

        return ReviewResource::collection($query->paginate(20)->withQueryString());
    }
}
