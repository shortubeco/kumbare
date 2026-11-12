<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Category;
use App\Models\PanelUser;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** İşletmeler için tanıtım sayfası ve başvuru formu. */
class BusinessApplicationController extends Controller
{
    public function show(): View
    {
        $pro = DB::table('plans')->where('code', 'pro')->first();

        return view('site.business', [
            'categories' => Category::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'freeLimit' => Setting::getInt('free_monthly_customer_limit', 100),
            'proMonthly' => $pro?->monthly_price,
            'proYearly' => $pro?->yearly_price,
            'seo' => [
                'title' => 'Restoranınızı Kumbare’ye ekleyin – Ücretsiz sadakat sistemi',
                'description' => 'Müşterilerinize harcamalarının bir kısmını ödül olarak geri verin. Menü, şube, kampanya ve raporlar dahil; ayda '
                    .Setting::getInt('free_monthly_customer_limit', 100).' müşteriye kadar ücretsiz.',
                'canonical' => route('web.business'),
                'robots' => 'index,follow',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Bot tuzağı: görünmeyen alan doluysa sessizce başarılı gibi davran.
        if ($request->filled('kb_bos')) {
            // Sessizce reddedilen gönderim denetim kaydına yazılır (gerçek başvuru kaybolursa fark edilsin).
            AuditLog::record(null, 'business.application_blocked', null, [
                'reason' => 'honeypot',
                'email' => mb_strtolower((string) $request->input('email')),
                'business_name' => (string) $request->input('business_name'),
            ]);

            return redirect()->route('web.business.thanks');
        }

        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:150'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
            'legal_name' => ['required', 'string', 'max:255'],
            'tax_office' => ['required', 'string', 'max:100'],
            'tax_number' => ['required', 'string', 'regex:/^\d{10,11}$/'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^[0-9+ ]{10,20}$/'],
            'password' => ['required', 'string', 'confirmed', Password::min(10)],
            'kvkk' => ['accepted'],
        ], [], [
            'business_name' => 'işletme adı',
            'category_id' => 'mutfak',
            'legal_name' => 'ticari unvan',
            'tax_office' => 'vergi dairesi',
            'tax_number' => 'vergi numarası',
            'kvkk' => 'aydınlatma metni onayı',
        ]);

        $email = mb_strtolower($data['email']);
        if (PanelUser::query()->whereRaw('lower(email) = ?', [$email])->exists()) {
            return back()->withInput()->withErrors(['email' => 'Bu e-posta adresiyle daha önce başvuru yapılmış.']);
        }

        DB::transaction(function () use ($data, $email) {
            $business = Business::query()->create([
                'name' => $data['business_name'],
                'slug' => Str::slug(strtr($data['business_name'], ['ı' => 'i', 'İ' => 'i'])).'-'.Str::lower(Str::random(5)),
                'category_id' => $data['category_id'],
                'status' => 'pending',
                'legal_name' => $data['legal_name'],
                'tax_office' => $data['tax_office'],
                'tax_number' => $data['tax_number'],
            ]);

            $owner = PanelUser::query()->create([
                'role' => PanelUser::ROLE_BUSINESS_OWNER,
                'business_id' => $business->id,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $email,
                'phone' => preg_replace('/\s+/', '', $data['phone']),
                'password' => $data['password'],
                'status' => 'active',
            ]);

            AuditLog::record($owner, 'business.applied', $business);
        });

        return redirect()->route('web.business.thanks');
    }

    public function thanks(): View
    {
        return view('site.business-thanks', [
            'seo' => ['title' => 'Başvurunuz alındı | Kumbare', 'description' => '', 'canonical' => route('web.business'), 'robots' => 'noindex,follow'],
        ]);
    }
}
