<?php

namespace App\Http\Controllers\Panel\Business;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Business;
use App\Models\PanelUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** İşletme paneli denetleyicilerinin ortak yardımcıları. Her sorgu işletme kimliğiyle sınırlandırılır. */
abstract class BaseController extends Controller
{
    protected function business(Request $request): Business
    {
        return $request->attributes->get('business');
    }

    protected function owner(): PanelUser
    {
        /** @var PanelUser */
        return Auth::guard('web')->user();
    }

    /** İşletmeye ait (silinmemiş) şube; başka işletmenin şubesi 404. */
    protected function branchOf(Request $request, int $id): Branch
    {
        return Branch::query()->where('business_id', $this->business($request)->id)
            ->where('status', '<>', 'deleted')->findOrFail($id);
    }
}
