<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Models\PanelUser;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Denetim kayıtları ve olağandışı müşteri numarası sorgu raporu. */
class AuditLogController extends AdminController
{
    public function index(Request $request): View
    {
        $action = trim((string) $request->query('islem', ''));
        $actor = trim((string) $request->query('kisi', ''));
        $from = $this->date($request->query('baslangic'));
        $to = $this->date($request->query('bitis'));

        $query = AuditLog::query();

        if ($action !== '') {
            $query->where('action', 'LIKE', addcslashes($action, '%_\\').'%');
        }
        if ($actor !== '') {
            $ids = PanelUser::query()->whereRaw('lower(email) LIKE ?', [$this->like($actor)])->pluck('id');
            $query->where('actor_type', 'panel_user')->whereIn('actor_id', $ids);
        }
        if ($from) {
            $query->where('created_at', '>=', $from->startOfDay());
        }
        if ($to) {
            $query->where('created_at', '<', $to->copy()->addDay()->startOfDay());
        }

        $logs = $query->orderByDesc('id')->paginate(50)->withQueryString();

        $actorIds = $logs->where('actor_type', 'panel_user')->pluck('actor_id')->unique();
        $actors = PanelUser::query()->whereIn('id', $actorIds)->get()->keyBy('id');

        return view('admin.audit.index', [
            'logs' => $logs,
            'actors' => $actors,
            'actions' => DB::table('audit_logs')->distinct()->orderBy('action')->pluck('action'),
            'filters' => ['islem' => $action, 'kisi' => $actor, 'baslangic' => $from?->toDateString(), 'bitis' => $to?->toDateString()],
        ]);
    }

    /**
     * Müşteri numaraları sıralı olduğu için toplu tarama riski vardır.
     * Personel bazında sorgu sayısı ve sorgulanan farklı müşteri sayısının
     * o personelin kazandırdığı işlem sayısına oranı gösterilir.
     */
    public function lookups(Request $request): View
    {
        $days = in_array((int) $request->query('gun'), [1, 7, 30], true) ? (int) $request->query('gun') : 1;
        $since = now()->subDays($days);

        $rows = DB::select("SELECT a.actor_id,
                COUNT(*) AS lookups,
                COUNT(DISTINCT a.subject_id) AS customers,
                (SELECT COUNT(*) FROM wallet_transactions t WHERE t.performed_by = a.actor_id AND t.created_at >= ?) AS transactions
            FROM audit_logs a
            WHERE a.action = 'customer.lookup' AND a.actor_type = 'panel_user' AND a.created_at >= ?
            GROUP BY a.actor_id
            ORDER BY COUNT(DISTINCT a.subject_id) DESC
            LIMIT 50", [$since, $since]);

        $users = PanelUser::query()->with('branch.business')->whereIn('id', collect($rows)->pluck('actor_id'))->get()->keyBy('id');

        return view('admin.audit.lookups', compact('rows', 'users', 'days'));
    }

    private function date(mixed $value): ?Carbon
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? Carbon::createFromFormat('!Y-m-d', $value) : null;
    }
}
