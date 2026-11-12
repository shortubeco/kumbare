<?php

namespace App\Http\Controllers\Panel;

use App\Domain\Counter\MonthlyCounterService;
use App\Http\Controllers\Controller;
use App\Models\PanelUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Kasadaki işlem ekranı: müşteri numarası → ödül kazandır / kullandır.
 * Sayfa tek ekrandır; adımlar panel-islem.js ile arka planda yürür.
 */
class TransactionScreenController extends Controller
{
    public function show(Request $request, MonthlyCounterService $counter): View
    {
        /** @var PanelUser $user */
        $user = Auth::guard('web')->user();
        $branch = $request->attributes->get('branch');

        return view('panel.transaction', [
            'branch' => $branch,
            'counter' => $counter->status($branch),
            'can' => [
                'lookup' => $user->hasPermission(PanelUser::PERM_LOOKUP) || $user->hasPermission(PanelUser::PERM_EARN) || $user->hasPermission(PanelUser::PERM_REDEEM),
                'earn' => $user->hasPermission(PanelUser::PERM_EARN),
                'redeem' => $user->hasPermission(PanelUser::PERM_REDEEM),
            ],
            'isManager' => $user->isBranchManager(),
        ]);
    }
}
