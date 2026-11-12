<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Campaign;
use App\Models\PanelUser;
use App\Support\ImageStore;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Şube yöneticisi: kampanyalar ve duyurular.
 * Oran kampanyası işlem ekranında seçilebilir (oranlar toplanmaz); duyuru yalnızca bilgilendirir.
 * Tarihler gün olarak girilir: başlangıç günü 00:00'da başlar, bitiş günü dahil (ertesi gün 00:00'da biter).
 */
class CampaignController extends Controller
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

    private function find(Request $request, int $id): Campaign
    {
        return Campaign::query()->where('branch_id', $this->branch($request)->id)->findOrFail($id);
    }

    public function index(Request $request): View
    {
        $branch = $this->branch($request);
        $tab = in_array($request->query('durum'), ['yaklasan', 'taslak', 'biten'], true) ? $request->query('durum') : 'yayinda';
        $now = now();

        $query = Campaign::query()->where('branch_id', $branch->id);
        match ($tab) {
            'yayinda' => $query->where('status', 'active')->where('starts_at', '<=', $now)->where('ends_at', '>', $now)->orderBy('ends_at'),
            'yaklasan' => $query->where('status', 'active')->where('starts_at', '>', $now)->orderBy('starts_at'),
            'taslak' => $query->where('status', 'draft')->where('ends_at', '>', $now)->orderByDesc('id'),
            'biten' => $query->where(fn ($w) => $w->where('status', 'ended')->orWhere('ends_at', '<=', $now))->orderByDesc('ends_at'),
        };

        $counts = DB::selectOne("SELECT
                COUNT(*) FILTER (WHERE status = 'active' AND starts_at <= now() AND ends_at > now()) AS yayinda,
                COUNT(*) FILTER (WHERE status = 'active' AND starts_at > now()) AS yaklasan,
                COUNT(*) FILTER (WHERE status = 'draft' AND ends_at > now()) AS taslak,
                COUNT(*) FILTER (WHERE status = 'ended' OR ends_at <= now()) AS biten
            FROM campaigns WHERE branch_id = ?", [$branch->id]);

        $usage = DB::table('wallet_transactions')->where('branch_id', $branch->id)->whereNotNull('campaign_id')
            ->selectRaw('campaign_id, COUNT(*) AS cnt, SUM(amount) AS total')->groupBy('campaign_id')->get()->keyBy('campaign_id');

        return view('panel.campaigns.index', [
            'branch' => $branch,
            'campaigns' => $query->paginate(20)->withQueryString(),
            'tab' => $tab,
            'counts' => $counts,
            'usage' => $usage,
        ]);
    }

    public function create(Request $request): View
    {
        return view('panel.campaigns.form', ['branch' => $this->branch($request), 'campaign' => null]);
    }

    public function edit(Request $request, int $campaign): View|RedirectResponse
    {
        $c = $this->find($request, $campaign);
        if ($c->status === 'ended' || $c->ends_at->isPast()) {
            return redirect()->route('panel.campaigns', ['durum' => 'biten'])->with('error', 'Sona eren kampanya düzenlenemez. Yeni bir kampanya oluşturabilirsiniz.');
        }

        return view('panel.campaigns.form', ['branch' => $this->branch($request), 'campaign' => $c]);
    }

    public function store(Request $request): RedirectResponse
    {
        $branch = $this->branch($request);
        $data = $this->validated($request);

        $c = new Campaign(['branch_id' => $branch->id, 'created_by' => $this->manager()->id] + $data['attrs']);
        $c->setWeekdayList($data['weekdays']);
        if ($request->hasFile('image')) {
            $c->image_path = ImageStore::store($request->file('image'), 'kampanyalar/'.$branch->id, 1200);
        }
        $c->save();
        AuditLog::record($this->manager(), 'campaign.created', $c, ['status' => $c->status]);

        return redirect()->route('panel.campaigns', ['durum' => $this->tabFor($c)])
            ->with('status', $c->status === 'draft' ? 'Kampanya taslak olarak kaydedildi.' : 'Kampanya yayımlandı.');
    }

    public function update(Request $request, int $campaign): RedirectResponse
    {
        $c = $this->find($request, $campaign);
        if ($c->status === 'ended' || $c->ends_at->isPast()) {
            return redirect()->route('panel.campaigns', ['durum' => 'biten'])->with('error', 'Sona eren kampanya düzenlenemez.');
        }
        $data = $this->validated($request, $c);

        $c->fill($data['attrs']);
        $c->setWeekdayList($data['weekdays']);
        if ($request->boolean('remove_image') && $c->image_path) {
            ImageStore::delete($c->image_path);
            $c->image_path = null;
        }
        if ($request->hasFile('image')) {
            ImageStore::delete($c->image_path);
            $c->image_path = ImageStore::store($request->file('image'), 'kampanyalar/'.$c->branch_id, 1200);
        }
        $c->save();
        AuditLog::record($this->manager(), 'campaign.updated', $c, ['status' => $c->status]);

        return redirect()->route('panel.campaigns', ['durum' => $this->tabFor($c)])->with('status', 'Kampanya güncellendi.');
    }

    /** Kampanyayı hemen sona erdirir (geri alınamaz; kayıt silinmez). */
    public function end(Request $request, int $campaign): RedirectResponse
    {
        $c = $this->find($request, $campaign);
        if ($c->status !== 'ended') {
            // Yayındaysa bitiş şimdiye çekilir; henüz başlamadıysa tarihler korunur (bitiş > başlangıç kuralı)
            $attrs = ['status' => 'ended'];
            if ($c->starts_at->isPast() && $c->ends_at->isFuture()) {
                $attrs['ends_at'] = now();
            }
            $c->update($attrs);
            AuditLog::record($this->manager(), 'campaign.ended', $c);
        }

        return redirect()->route('panel.campaigns', ['durum' => 'biten'])->with('status', '"'.$c->title.'" sona erdirildi.');
    }

    private function tabFor(Campaign $c): string
    {
        return match (true) {
            $c->status === 'draft' => 'taslak',
            $c->starts_at->isFuture() => 'yaklasan',
            default => 'yayinda',
        };
    }

    /** @return array{attrs: array, weekdays: array} */
    private function validated(Request $request, ?Campaign $existing = null): array
    {
        $request->merge(['reward_rate' => $request->filled('reward_rate')
            ? str_replace(['%', ' '], '', str_replace(',', '.', (string) $request->input('reward_rate'))) : null]);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'terms' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::in([Campaign::TYPE_ANNOUNCEMENT, Campaign::TYPE_RATE_OVERRIDE])],
            'reward_rate' => ['nullable', 'required_if:type,rate_override', 'numeric', 'min:0', 'max:100', 'regex:/^\d{1,3}(\.\d{1,2})?$/'],
            'starts_on' => ['required', 'date_format:Y-m-d', $existing ? 'nullable' : 'after_or_equal:today'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on', 'before_or_equal:'.now()->addYear()->toDateString()],
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'new_customers_only' => ['nullable', 'boolean'],
            'birthday_only' => ['nullable', 'boolean'],
            'publish' => ['required', Rule::in(['draft', 'active'])],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
        ], [
            'reward_rate.required_if' => 'Oran kampanyası için ödül oranını yazın.',
            'reward_rate.regex' => 'Oranı en fazla iki ondalıkla yazın (örn. 12,5).',
            'starts_on.after_or_equal' => 'Başlangıç tarihi bugünden önce olamaz.',
            'ends_on.after_or_equal' => 'Bitiş tarihi başlangıçtan önce olamaz.',
            'ends_on.before_or_equal' => 'Kampanya en fazla bir yıl sürebilir.',
            'image.mimes' => 'Görsel JPG, PNG veya WEBP olmalıdır.',
            'image.max' => 'Görsel en fazla 6 MB olabilir.',
        ], [
            'title' => 'başlık', 'description' => 'açıklama', 'terms' => 'koşullar', 'type' => 'kampanya türü',
            'reward_rate' => 'ödül oranı', 'starts_on' => 'başlangıç tarihi', 'ends_on' => 'bitiş tarihi', 'image' => 'görsel',
        ]);

        $isRate = $data['type'] === Campaign::TYPE_RATE_OVERRIDE;
        $weekdays = array_values(array_unique(array_map('intval', $data['weekdays'] ?? [])));
        sort($weekdays);
        if (count($weekdays) === 7) {
            $weekdays = []; // her gün = gün kısıtı yok
        }

        $tz = config('app.timezone');

        return [
            'attrs' => [
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'terms' => $data['terms'] ?? null,
                'type' => $data['type'],
                'reward_rate' => $isRate ? number_format((float) $data['reward_rate'], 2, '.', '') : null,
                'new_customers_only' => $request->boolean('new_customers_only'),
                'birthday_only' => $request->boolean('birthday_only'),
                'starts_at' => Carbon::createFromFormat('!Y-m-d', $data['starts_on'], $tz),
                'ends_at' => Carbon::createFromFormat('!Y-m-d', $data['ends_on'], $tz)->addDay(),
                'status' => $data['publish'],
            ],
            'weekdays' => $weekdays,
        ];
    }
}
