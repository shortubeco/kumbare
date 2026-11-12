<?php

namespace App\Http\Controllers\Web;

use App\Domain\Catalog\BranchSearch;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\City;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HomeController extends Controller
{
    /** Ana sayfada kendi "en iyiler" listesi olan mutfaklar */
    private const FEATURED_CATEGORIES = ['kebap', 'pide-lahmacun', 'kahvalti', 'ev-yemekleri', 'doner', 'tatli-pastane'];

    /** Nüfusa göre büyük şehirler (plaka kodları) */
    private const FEATURED_CITIES = [34, 6, 35, 16, 7, 42, 1, 27, 63, 41, 33, 21];

    public function __invoke(BranchSearch $search): View
    {
        // Ana sayfa blokları 10 dakika önbellekte tutulur.
        $data = Cache::remember('web.home.v2', now()->addMinutes(10), function () use ($search) {
            $categories = Category::query()->where('is_active', true)
                ->select('categories.*', DB::raw('(SELECT COUNT(*) FROM branches b JOIN businesses bu ON bu.id = b.business_id
                    WHERE bu.category_id = categories.id AND b.status = \'active\' AND bu.status = \'approved\') AS branch_count'))
                ->orderBy('sort_order')->get();

            $categoryLists = $categories
                ->filter(fn ($c) => in_array($c->slug, self::FEATURED_CATEGORIES, true))
                ->sortBy(fn ($c) => array_search($c->slug, self::FEATURED_CATEGORIES, true))
                ->map(fn ($c) => [
                    'category' => $c,
                    // Ağırlıklı puan: az yorumlu yüksek puanlar ortalamaya doğru çekilir
                    // (5 değerlendirmelik "güven payı", genel ortalama 7.5 kabul edilir).
                    'branches' => $search->query(['category' => $c->id])
                        ->where('branches.rating_count', '>', 0)
                        ->reorder()
                        ->orderByRaw('(branches.rating_count / (branches.rating_count + 5.0)) * branches.rating_avg
                                      + (5.0 / (branches.rating_count + 5.0)) * 7.5 DESC')
                        ->orderBy('branches.id')
                        ->limit(5)->get(),
                ])
                ->filter(fn ($row) => $row['branches']->isNotEmpty())
                ->values();

            return [
                'sponsored' => $search->sponsored()->inRandomOrder()->limit(6)->get(),
                'topScore' => $search->query([], 'score')
                    ->where('branches.rating_count', '>=', BranchSearch::MIN_REVIEWS_FOR_SCORE_RANKING)->limit(5)->get(),
                'mostReviewed' => $search->query([], 'most_reviewed')->where('branches.rating_count', '>', 0)->limit(5)->get(),
                'topRate' => $search->query([], 'rate_desc')->where('branches.rating_count', '>', 0)->limit(5)->get(),
                'popular' => $search->query([], 'popular')->limit(5)->get(),
                'withCampaign' => $search->query(['has_campaign' => true], 'popular')->limit(6)->get(),
                'newest' => $search->query([], 'newest')->limit(5)->get(),
                'categories' => $categories,
                'categoryLists' => $categoryLists,
                'cities' => City::query()
                    ->select('cities.*', DB::raw('(SELECT COUNT(*) FROM branches b JOIN businesses bu ON bu.id = b.business_id
                        WHERE b.city_id = cities.id AND b.status = \'active\' AND bu.status = \'approved\') AS branch_count'))
                    ->orderBy('name')
                    ->get(),
                'stats' => [
                    'branches' => DB::table('branches')->where('status', 'active')->count(),
                    'cities' => DB::table('branches')->where('status', 'active')->distinct()->count('city_id'),
                    'reviews' => DB::table('reviews')->where('status', 'published')->count(),
                ],
            ];
        });

        $featuredCities = collect(self::FEATURED_CITIES)
            ->map(fn ($id) => $data['cities']->firstWhere('id', $id))
            ->filter(fn ($c) => $c && $c->branch_count > 0)
            ->values();

        return view('site.home', $data + ['featuredCities' => $featuredCities]);
    }
}
