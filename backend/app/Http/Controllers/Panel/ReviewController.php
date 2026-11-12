<?php

namespace App\Http\Controllers\Panel;

use App\Domain\Reviews\RatingAggregator;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\PanelUser;
use App\Models\Review;
use App\Models\ReviewReply;
use App\Models\ReviewReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Şube yöneticisi: değerlendirmeler. Yorum silinemez/düzenlenemez; yöneticinin
 * tek cevap hakkı vardır (düzenlenebilir). Uygunsuz yorum platforma şikâyet edilir.
 */
class ReviewController extends Controller
{
    private function branch(Request $request): Branch
    {
        return $request->attributes->get('branch');
    }

    private function manager(): PanelUser
    {
        /** @var PanelUser */
        return Auth::guard('web')->user();
    }

    private function find(Request $request, int $id): Review
    {
        return Review::query()->where('branch_id', $this->branch($request)->id)->where('status', 'published')->findOrFail($id);
    }

    public function index(Request $request, RatingAggregator $ratings): View
    {
        $branch = $this->branch($request);
        $filter = in_array($request->query('filtre'), ['cevapsiz', 'dusuk'], true) ? $request->query('filtre') : 'tumu';
        $sort = $request->query('siralama') === 'puan' ? 'puan' : 'yeni';

        $query = Review::query()->where('branch_id', $branch->id)->where('status', 'published')->with(['customer', 'reply']);
        if ($filter === 'cevapsiz') {
            $query->whereDoesntHave('reply');
        } elseif ($filter === 'dusuk') {
            $query->where('score', '<=', 5);
        }
        $sort === 'puan' ? $query->orderBy('score')->orderByDesc('created_at') : $query->orderByDesc('created_at');

        $reported = ReviewReport::query()->where('reporter_type', 'panel_user')->where('reporter_id', $this->manager()->id)
            ->where('status', 'open')->pluck('review_id')->all();

        return view('panel.reviews.index', [
            'branch' => $branch,
            'reviews' => $query->paginate(20)->withQueryString(),
            'distribution' => $ratings->distribution($branch),
            'filter' => $filter,
            'sort' => $sort,
            'unanswered' => Review::query()->where('branch_id', $branch->id)->where('status', 'published')->whereDoesntHave('reply')->count(),
            'reported' => $reported,
        ]);
    }

    public function reply(Request $request, int $review): RedirectResponse
    {
        $r = $this->find($request, $review);
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:1000']], [], ['body' => 'cevap']);

        $reply = ReviewReply::query()->updateOrCreate(
            ['review_id' => $r->id],
            ['panel_user_id' => $this->manager()->id, 'body' => $data['body']]
        );
        AuditLog::record($this->manager(), $reply->wasRecentlyCreated ? 'review.replied' : 'review.reply_updated', $r);

        return back()->with('status', 'Cevabınız yayımlandı; sitede ve uygulamada yorumun altında görünür.');
    }

    public function report(Request $request, int $review): RedirectResponse
    {
        $r = $this->find($request, $review);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']], [], ['reason' => 'şikâyet gerekçesi']);

        ReviewReport::query()->firstOrCreate(
            ['review_id' => $r->id, 'reporter_type' => 'panel_user', 'reporter_id' => $this->manager()->id, 'status' => 'open'],
            ['reason' => $data['reason']]
        );
        AuditLog::record($this->manager(), 'review.reported', $r, ['reason' => $data['reason']]);

        return back()->with('status', 'Şikâyetiniz Kumbare ekibine iletildi. İnceleme sonucunda yorum kaldırılabilir.');
    }
}
