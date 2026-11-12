<?php

namespace App\Http\Controllers\Web;

use App\Domain\Catalog\BranchSearch;
use App\Domain\Reviews\RatingAggregator;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Campaign;
use App\Models\MenuCategory;
use App\Models\Review;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function show(Request $request, BranchSearch $search, RatingAggregator $ratings, string $isletme, string $sube): View
    {
        $business = Business::query()->where('slug', $isletme)->where('status', 'approved')->firstOrFail();

        /** @var Branch $branch */
        $branch = $search->query()
            ->where('branches.business_id', $business->id)
            ->where('branches.slug', $sube)
            ->firstOrFail();
        $branch->load(['hours', 'photos']);

        $campaigns = Campaign::query()->where('branch_id', $branch->id)->running()->orderBy('ends_at')->get();

        $menu = MenuCategory::query()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')
            ->with('items')
            ->get()
            ->filter(fn (MenuCategory $c) => $c->items->isNotEmpty())
            ->values();

        $otherBranches = Branch::query()
            ->publiclyVisible()
            ->where('business_id', $business->id)
            ->where('id', '!=', $branch->id)
            ->with('district.city')
            ->orderBy('name')
            ->get();

        $reviewSort = in_array($request->query('yorumlar'), ['yeni', 'eski', 'puan'], true) ? $request->query('yorumlar') : 'yeni';
        $reviewQuery = Review::query()
            ->where('branch_id', $branch->id)
            ->where('status', 'published')
            ->with(['customer', 'reply']);
        match ($reviewSort) {
            'eski' => $reviewQuery->orderBy('created_at'),
            'puan' => $reviewQuery->orderByDesc('score')->orderByDesc('created_at'),
            default => $reviewQuery->orderByDesc('created_at'),
        };
        $reviews = $reviewQuery->paginate(10, ['*'], 'sayfa')->withQueryString()->fragment('degerlendirmeler');

        $now = now();
        $today = (int) $now->dayOfWeekIso;
        $todayHours = $branch->hours->firstWhere('weekday', $today);
        $openNow = $this->isOpen($todayHours, $now->format('H:i'));

        $title = $business->name.' '.$branch->name;
        $place = $branch->district->displayName().', '.$branch->district->city->name;
        $description = $title.' ('.$place.'): menü, fiyatlar, '
            .((float) $branch->reward_rate > 0 ? Format::rate($branch->reward_rate).' ödül oranı' : 'ödül bilgisi')
            .($branch->rating_count > 0 ? ', '.Format::score($branch->rating_avg).'/10 puan ('.$branch->rating_count.' değerlendirme)' : '')
            .', adres ve çalışma saatleri.';

        return view('site.branches.show', [
            'branch' => $branch,
            'business' => $business,
            'campaigns' => $campaigns,
            'menu' => $menu,
            'otherBranches' => $otherBranches,
            'reviews' => $reviews,
            'reviewSort' => $reviewSort,
            'distribution' => $ratings->distribution($branch),
            'today' => $today,
            'openNow' => $openNow,
            'seo' => [
                'title' => $title.' – Menü, ödül oranı ve yorumlar | Kumbare',
                'description' => mb_substr($description, 0, 158),
                'canonical' => route('web.branch', [$business->slug, $branch->slug]).($reviews->currentPage() > 1 ? '?sayfa='.$reviews->currentPage() : ''),
                'robots' => $request->hasAny(['yorumlar']) ? 'noindex,follow' : 'index,follow',
            ],
        ]);
    }

    /** Gece yarısını geçen saatleri de (ör. 12:00–02:00) dikkate alır. */
    private function isOpen($hours, string $nowHm): ?bool
    {
        if (! $hours) {
            return null;
        }
        if ($hours->is_closed) {
            return false;
        }
        $open = substr($hours->opens_at, 0, 5);
        $close = substr($hours->closes_at, 0, 5);

        return $close > $open
            ? ($nowHm >= $open && $nowHm < $close)
            : ($nowHm >= $open || $nowHm < $close);
    }
}
