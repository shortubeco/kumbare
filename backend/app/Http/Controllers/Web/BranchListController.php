<?php

namespace App\Http\Controllers\Web;

use App\Domain\Catalog\BranchSearch;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\City;
use App\Models\District;
use App\Support\TurkishText;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Restoran listeleme: /restoranlar, /restoranlar/{il}, /restoranlar/{il}/{ilce}, /kategori/{mutfak}.
 * Filtreler sorgu parametreleriyle (Türkçe adlarla) taşınır.
 */
class BranchListController extends Controller
{
    /** Sitedeki sıralama adı → BranchSearch sıralaması */
    public const SORTS = [
        'populer' => ['popular', 'Popüler'],
        'odul' => ['rate_desc', 'En yüksek ödül'],
        'odul-artan' => ['rate_asc', 'En düşük ödül'],
        'puan' => ['score', 'En yüksek puan'],
        'yorum' => ['most_reviewed', 'En çok değerlendirilen'],
        'yeni' => ['newest', 'En yeni'],
        'yakin' => ['nearest', 'Yakınımdaki'],
    ];

    public function index(Request $request, BranchSearch $search): View
    {
        $city = $request->filled('il') ? City::query()->where('slug', $request->query('il'))->first() : null;

        return $this->render($request, $search, $city, null, null);
    }

    public function city(Request $request, BranchSearch $search, string $il): View
    {
        $city = City::query()->where('slug', $il)->firstOrFail();

        return $this->render($request, $search, $city, null, null);
    }

    public function district(Request $request, BranchSearch $search, string $il, string $ilce): View
    {
        $city = City::query()->where('slug', $il)->firstOrFail();
        $district = District::query()->where('city_id', $city->id)->where('slug', $ilce)->firstOrFail();

        return $this->render($request, $search, $city, $district, null);
    }

    public function category(Request $request, BranchSearch $search, string $mutfak): View
    {
        $category = Category::query()->where('slug', $mutfak)->where('is_active', true)->firstOrFail();
        $city = $request->filled('il') ? City::query()->where('slug', $request->query('il'))->first() : null;

        return $this->render($request, $search, $city, null, $category);
    }

    private function render(Request $request, BranchSearch $search, ?City $city, ?District $district, ?Category $category): View
    {
        $category ??= $request->filled('mutfak')
            ? Category::query()->where('slug', $request->query('mutfak'))->first()
            : null;
        $district ??= ($city && $request->filled('ilce'))
            ? District::query()->where('city_id', $city->id)->where('slug', $request->query('ilce'))->first()
            : null;

        $sortKey = array_key_exists($request->query('siralama'), self::SORTS) ? $request->query('siralama') : 'populer';
        $lat = is_numeric($request->query('lat')) ? (float) $request->query('lat') : null;
        $lng = is_numeric($request->query('lng')) ? (float) $request->query('lng') : null;
        if ($sortKey === 'yakin' && ($lat === null || $lng === null)) {
            $sortKey = 'populer';
        }

        $filters = array_filter([
            'city' => $city?->id,
            'district' => $district?->id,
            'category' => $category?->id,
            'min_rate' => is_numeric($request->query('min_odul')) ? max(0, min(100, (float) $request->query('min_odul'))) : null,
            'min_score' => is_numeric($request->query('min_puan')) ? max(1, min(10, (float) $request->query('min_puan'))) : null,
            'has_campaign' => $request->boolean('kampanya') ?: null,
            'new' => $request->boolean('yeni') ?: null,
            'q' => mb_substr(trim((string) $request->query('q')), 0, 80) ?: null,
            'lat' => $lat,
            'lng' => $lng,
        ], fn ($v) => $v !== null);

        $branches = $search->query($filters, self::SORTS[$sortKey][0])
            ->paginate(20, ['*'], 'sayfa')
            ->withQueryString();

        // Başlık, açıklama ve canonical adres
        $place = $district ? $district->displayName() : $city?->name;
        if ($category && $place) {
            $h1 = TurkishText::locativeAdjective($place).' '.mb_strtolower(strtr($category->name, ['I' => 'ı', 'İ' => 'i']), 'UTF-8').' restoranları';
        } elseif ($category) {
            $h1 = $category->name.' restoranları';
        } elseif ($place) {
            $h1 = TurkishText::locativeAdjective($place).' restoranlar';
        } else {
            $h1 = 'Restoranlar';
        }

        $total = $branches->total();
        $intro = $place
            ? TurkishText::locative($place)." Kumbare'ye katılan {$total} restoranı ödül oranına, puanına ve mutfağına göre karşılaştır."
            : "Kumbare'ye katılan {$total} restoranı ödül oranına, puanına ve mutfağına göre karşılaştır.";

        $basePath = match (true) {
            $category !== null && $city === null => route('web.category', $category->slug),
            $district !== null => route('web.district', [$city->slug, $district->slug]),
            $city !== null => route('web.city', $city->slug),
            default => route('web.branches'),
        };
        $canonical = $basePath.($branches->currentPage() > 1 ? '?sayfa='.$branches->currentPage() : '');

        // Yol (path) ile ifade edilemeyen filtreler varsa sayfa dizine eklenmez.
        $extraParams = collect($request->query())->except(['sayfa'])->filter(fn ($v) => $v !== null && $v !== '')->keys();
        $pathCoversCity = $request->routeIs('web.city', 'web.district');
        $indexable = $extraParams->isEmpty() || ($extraParams->all() === ['il'] && $pathCoversCity);

        return view('site.branches.index', [
            'branches' => $branches,
            'city' => $city,
            'district' => $district,
            'category' => $category,
            'cities' => City::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'districts' => $city ? District::query()->where('city_id', $city->id)->orderBy('name')->get() : collect(),
            'categories' => Category::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'sortKey' => $sortKey,
            'sorts' => self::SORTS,
            'filters' => $request->query(),
            'h1' => $h1,
            'intro' => $intro,
            'seo' => [
                'title' => $h1.($branches->currentPage() > 1 ? ' – Sayfa '.$branches->currentPage() : '').' ve ödül oranları | Kumbare',
                'description' => mb_substr($intro.' Her harcamanın bir kısmı ödül olarak o restoranın kumbarasına döner.', 0, 158),
                'canonical' => $canonical,
                'robots' => $indexable ? 'index,follow' : 'noindex,follow',
            ],
        ]);
    }
}
