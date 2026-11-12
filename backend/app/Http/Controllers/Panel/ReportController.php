<?php

namespace App\Http\Controllers\Panel;

use App\Domain\Reports\BranchReport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Panel\Concerns\ReportRange;
use App\Models\AuditLog;
use App\Models\PanelUser;
use App\Support\Labels;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Şube raporları: şube yöneticisi + "raporları görüntüleme" yetkili çalışan.
 * Tarih aralığı hazır seçeneklerle veya elle; tablolar CSV olarak indirilebilir.
 */
class ReportController extends Controller
{
    use ReportRange;

    /** Geriye dönük uyum: görünüm ve testler PRESETS sabitini kullanır. */
    public const PRESETS = ['7g' => 'Son 7 gün', '30g' => 'Son 30 gün', 'buay' => 'Bu ay', 'gecenay' => 'Geçen ay', '90g' => 'Son 90 gün', 'buyil' => 'Bu yıl'];

    private function user(): PanelUser
    {
        /** @var PanelUser $u */
        $u = Auth::guard('web')->user();
        abort_unless($u->hasPermission(PanelUser::PERM_VIEW_REPORTS), 403, 'Raporları görüntüleme yetkiniz yok.');

        return $u;
    }

    public function index(Request $request): View|StreamedResponse
    {
        $user = $this->user();
        $branch = $request->attributes->get('branch');
        [$from, $to, $preset, $error] = $this->range($request);
        $report = new BranchReport($branch, $from, $to);

        if ($request->query('indir')) {
            return $this->csv($request->query('indir'), $report, $user);
        }

        return view('panel.reports', [
            'branch' => $branch,
            'from' => $from,
            'to' => $to,
            'preset' => $preset,
            'presets' => self::PRESETS,
            'rangeError' => $error,
            'g' => $report->granularity(),
            'summary' => $report->summary(),
            'periods' => $report->periods(),
            'campaigns' => $report->campaigns(),
            'customers' => $report->topCustomers(),
            'staff' => $user->isBranchManager() ? $report->staff() : null,
        ]);
    }

    /** Excel uyumlu CSV: UTF-8 BOM, ";" ayırıcı, ondalıkta virgül. */
    private function csv(string $what, BranchReport $r, PanelUser $user): StreamedResponse
    {
        $money = fn ($v) => number_format((float) $v, 2, ',', '');
        $label = fn ($start) => $this->periodLabel($start, $r->granularity());

        [$name, $head, $rows] = match ($what) {
            'donemler' => ['donemler', ['Dönem', 'İşlem', 'Farklı müşteri', 'Yeni', 'Tekrar gelen', 'Harcama (TL)', 'Kazandırılan ödül (TL)', 'Kullanılan ödül (TL)'],
                array_map(fn ($p) => [$label($p->start), $p->tx, $p->customers, $p->new, $p->returning, $money($p->spend), $money($p->earned), $money($p->redeemed)], $r->periods())],
            'kampanyalar' => ['kampanyalar', ['Kampanya', 'Oran', 'Kazanım işlemi', 'Farklı müşteri', 'Harcama (TL)', 'Verilen ödül (TL)'],
                array_map(fn ($c) => [$c->title, $c->reward_rate !== null ? \App\Support\Format::rate($c->reward_rate) : 'Şube oranı', $c->tx, $c->customers, $money($c->spend), $money($c->earned)], $r->campaigns())],
            'musteriler' => ['musteriler', ['Müşteri no', 'Ad soyad', 'Ziyaret', 'Harcama (TL)', 'Kazanılan ödül (TL)', 'Kullanılan ödül (TL)', 'Güncel bakiye (TL)'],
                array_map(fn ($c) => [$c->customer_no, $c->first_name.' '.$c->last_name, $c->visits, $money($c->spend), $money($c->earned), $money($c->redeemed), $money($c->balance)], $r->topCustomers(500))],
            default => abort(404),
        };

        AuditLog::record($user, 'report.exported', $r->branch, ['table' => $name, 'from' => $r->from->toDateString(), 'to' => $r->to->toDateString()]);

        $file = 'kumbare-'.$name.'-'.$r->from->format('Ymd').'-'.$r->to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($head, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $head, ';');
            foreach ($rows as $row) {
                fputcsv($out, $row, ';');
            }
            fclose($out);
        }, $file, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function periodLabel(CarbonImmutable $start, string $g): string
    {
        return match ($g) {
            'day' => Labels::date($start),
            'week' => Labels::date($start).' haftası',
            default => Labels::month($start),
        };
    }
}
