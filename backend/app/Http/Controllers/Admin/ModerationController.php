<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Notifications\PanelNotifier;
use App\Domain\Reviews\ReviewService;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Review;
use App\Models\ReviewReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Kampanya moderasyonu ve yorum şikâyetleri. */
class ModerationController extends AdminController
{
    public function campaigns(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $scope = $request->query('durum') === 'all' ? 'all' : 'running';

        $query = Campaign::query()->with('branch.business');
        if ($scope === 'running') {
            $query->where('status', 'active')->where('ends_at', '>', now());
        }
        if ($q !== '') {
            $like = $this->like($q);
            $query->where(function ($w) use ($like) {
                $w->whereRaw('lower(title) LIKE ?', [$like])
                    ->orWhereRaw('lower(description) LIKE ?', [$like])
                    ->orWhereHas('branch.business', fn ($b) => $b->whereRaw('lower(name) LIKE ?', [$like]));
            });
        }

        return view('admin.campaigns', [
            'campaigns' => $query->orderByDesc('id')->paginate(25)->withQueryString(),
            'q' => $q,
            'scope' => $scope,
        ]);
    }

    /** Uygunsuz kampanyayı yayından kaldırır (sona erdirir); kayıt silinmez. */
    public function removeCampaign(Request $request, Campaign $campaign, PanelNotifier $notifier): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']], [], ['reason' => 'kaldırma gerekçesi']);

        if ($campaign->status === 'ended') {
            return back()->with('error', 'Bu kampanya zaten sona ermiş.');
        }

        $campaign->update(['status' => 'ended']);
        AuditLog::record($this->admin(), 'campaign.removed', $campaign, ['reason' => $data['reason']]);
        $notifier->notifyBranchManager($campaign->branch, 'campaign_removed', [
            'campaign_id' => $campaign->id, 'title' => $campaign->title, 'reason' => $data['reason'],
        ]);

        return back()->with('status', "\"{$campaign->title}\" kampanyası yayından kaldırıldı.");
    }

    public function reports(Request $request): View
    {
        $scope = $request->query('durum') === 'resolved' ? 'resolved' : 'open';

        // Şikâyetler yorum bazında gruplanır: bir yoruma gelen tüm şikâyetler tek kartta.
        $reviewIds = ReviewReport::query()
            ->where('status', $scope)
            ->select('review_id', DB::raw('MIN(created_at) AS first_at'), DB::raw('COUNT(*) AS cnt'))
            ->groupBy('review_id')
            ->orderBy($scope === 'open' ? 'first_at' : DB::raw('MAX(resolved_at)'), $scope === 'open' ? 'asc' : 'desc')
            ->paginate(20)->withQueryString();

        $reviews = Review::query()->with(['branch.business', 'customer', 'reply'])
            ->whereIn('id', $reviewIds->pluck('review_id'))->get()->keyBy('id');

        $reports = ReviewReport::query()->whereIn('review_id', $reviewIds->pluck('review_id'))
            ->where('status', $scope)->orderBy('created_at')->get()->groupBy('review_id');

        return view('admin.reports', compact('reviewIds', 'reviews', 'reports', 'scope'));
    }

    public function removeReview(Request $request, Review $review, ReviewService $service): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']], [], ['reason' => 'kaldırma gerekçesi']);
        $service->remove($this->admin(), $review, $data['reason']);

        return back()->with('status', 'Yorum kaldırıldı; şubenin puanı yeniden hesaplandı.');
    }

    public function dismissReports(Review $review, ReviewService $service): RedirectResponse
    {
        $service->dismissReports($this->admin(), $review);

        return back()->with('status', 'Şikâyetler kapatıldı; yorum yayında kalıyor.');
    }
}
