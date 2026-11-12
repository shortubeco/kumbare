<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\PanelNotification;
use App\Models\PanelUser;
use App\Support\Format;
use App\Support\Labels;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Panel bildirimleri (push yok): limit uyarıları, havale ve abonelik sonuçları, onay sonuçları. */
class NotificationController extends Controller
{
    public function index(): View
    {
        /** @var PanelUser $user */
        $user = Auth::guard('web')->user();

        $items = PanelNotification::query()->where('panel_user_id', $user->id)
            ->orderByDesc('id')->paginate(30);

        $unreadIds = $items->filter(fn ($n) => $n->read_at === null)->pluck('id');
        // Sayfada görülen bildirimler okundu sayılır (vurgu bu sayfa gösteriminde kalır)
        if ($unreadIds->isNotEmpty()) {
            PanelNotification::query()->whereIn('id', $unreadIds)->update(['read_at' => now()]);
        }

        $isManager = $user->isBranchManager();

        return view('panel.notifications', [
            'items' => $items->through(function ($n) use ($unreadIds, $isManager) {
                $desc = self::describe($n);
                if (! $isManager) {
                    $desc['url'] = null; // bağlantılar yönetici ekranlarına gider
                }

                return ['n' => $n, 'unread' => $unreadIds->contains($n->id)] + $desc;
            }),
        ]);
    }

    public function readAll(): RedirectResponse
    {
        PanelNotification::query()->where('panel_user_id', Auth::guard('web')->id())->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }

    /** Bildirim türünü Türkçe başlık, açıklama, renk ve bağlantıya çevirir. */
    public static function describe(PanelNotification $n): array
    {
        $d = is_array($n->data) ? $n->data : (json_decode((string) $n->data, true) ?: []);
        $money = fn ($k) => isset($d[$k]) ? Format::money($d[$k]) : '';
        $purpose = ($d['purpose'] ?? '') === 'sponsorship' ? 'Sponsorluk' : 'Profesyonel paket';

        return match ($n->type) {
            'threshold_1', 'threshold_2' => ['title' => 'Aylık müşteri limitine yaklaşıldı', 'kind' => 'warn',
                'text' => 'Bu ay '.($d['unique_customers'] ?? '?').' / '.($d['limit'] ?? '?').' müşteriye ödül kazandırıldı.', 'url' => route('panel.package')],
            'limit_reached' => ['title' => 'Aylık müşteri limiti doldu', 'kind' => 'bad',
                'text' => 'Bu ay ilk kez gelen müşterilere ödül kazandırılamaz. Profesyonel pakete geçerek sınırı kaldırabilirsiniz.', 'url' => route('panel.package')],
            'payment_approved' => ['title' => $purpose.' havalesi onaylandı', 'kind' => 'ok',
                'text' => isset($d['ends_at']) ? 'Profesyonel paket '.Labels::date($d['ends_at']).' tarihine kadar geçerli.'
                    : (isset($d['sponsorship_month']) ? Labels::month($d['sponsorship_month']).' ayı sponsorluğunuz onaylandı.' : 'Havale bildiriminiz onaylandı.'),
                'url' => ($d['purpose'] ?? '') === 'sponsorship' ? route('panel.sponsorship') : route('panel.package')],
            'payment_rejected' => ['title' => $purpose.' havalesi onaylanmadı', 'kind' => 'bad',
                'text' => 'Gerekçe: '.($d['note'] ?? '—'), 'url' => route('panel.package')],
            'subscription_expiring' => ['title' => 'Profesyonel paketin süresi bitiyor', 'kind' => 'warn',
                'text' => isset($d['ends_at']) ? Labels::date($d['ends_at']).' tarihinde Ücretsiz pakete dönülecek. Yenilemek için havale bildirimi gönderin.' : '', 'url' => route('panel.package')],
            'subscription_expired' => ['title' => 'Profesyonel paketin süresi doldu', 'kind' => 'bad',
                'text' => 'Şubeniz Ücretsiz pakete döndü.', 'url' => route('panel.package')],
            'subscription_cancelled' => ['title' => 'Profesyonel paket iptal edildi', 'kind' => 'bad',
                'text' => 'Gerekçe: '.($d['reason'] ?? '—'), 'url' => route('panel.package')],
            'sponsorship_cancelled' => ['title' => 'Sponsorluk iptal edildi', 'kind' => 'bad',
                'text' => (isset($d['month']) ? Labels::month($d['month']).' · ' : '').'Gerekçe: '.($d['reason'] ?? '—'), 'url' => route('panel.sponsorship')],
            'campaign_removed' => ['title' => 'Kampanya yayından kaldırıldı', 'kind' => 'bad',
                'text' => '"'.($d['title'] ?? '').'" — Gerekçe: '.($d['reason'] ?? '—'), 'url' => route('panel.campaigns', ['durum' => 'biten'])],
            'branch_status_changed' => ['title' => 'Şube durumu değişti', 'kind' => ($d['status'] ?? '') === 'active' ? 'ok' : 'bad',
                'text' => (($d['status'] ?? '') === 'active' ? 'Şubeniz yeniden aktif.' : 'Şubeniz pasif yapıldı.').(! empty($d['reason']) ? ' Gerekçe: '.$d['reason'] : ''), 'url' => null],
            'approval_approved', 'approval_rejected' => (function () use ($n, $d, $money) {
                $ok = $n->type === 'approval_approved';
                $what = ($d['request_type'] ?? '') === 'correction' ? 'Düzeltme' : 'Ödül kullanım';

                return [
                    'title' => $what.' isteği '.($ok ? 'müşteri tarafından onaylandı' : 'onaylanmadı'),
                    'kind' => $ok ? 'ok' : 'bad',
                    'text' => isset($d['amount']) ? 'Tutar: '.$money('amount') : '',
                    'url' => ($d['request_type'] ?? '') === 'correction' ? route('panel.corrections', ['durum' => 'all']) : null,
                ];
            })(),
            default => ['title' => 'Bildirim', 'kind' => '', 'text' => '', 'url' => null],
        };
    }
}
