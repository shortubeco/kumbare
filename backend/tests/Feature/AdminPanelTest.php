<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Campaign;
use App\Models\PanelUser;
use App\Models\PaymentNotification;
use App\Models\Review;
use App\Models\ReviewReport;
use App\Models\Setting;
use App\Models\Sponsorship;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    private function makeAdmin(): PanelUser
    {
        $n = $this->next();

        return PanelUser::query()->create([
            'role' => PanelUser::ROLE_PLATFORM_ADMIN,
            'first_name' => 'Platform',
            'last_name' => "Yönetici{$n}",
            'email' => "admin{$n}@test.local",
            'password' => 'secret-password-123',
            'status' => 'active',
        ]);
    }

    private function makeApplication(): Business
    {
        $n = $this->next();
        $business = Business::query()->create([
            'name' => "Başvuru {$n}", 'slug' => "basvuru-{$n}", 'status' => 'pending',
            'legal_name' => 'Deneme Gıda Ltd.', 'tax_office' => 'Kadıköy', 'tax_number' => '1234567890',
        ]);
        PanelUser::query()->create([
            'role' => PanelUser::ROLE_BUSINESS_OWNER, 'business_id' => $business->id,
            'first_name' => 'Sahip', 'last_name' => "Test{$n}", 'email' => "owner{$n}@test.local",
            'password' => 'secret-password', 'status' => 'active',
        ]);

        return $business;
    }

    private function makePayment(Branch $branch, PanelUser $manager, array $attrs = []): PaymentNotification
    {
        return PaymentNotification::query()->create(array_merge([
            'branch_id' => $branch->id,
            'submitted_by' => $manager->id,
            'purpose' => 'subscription',
            'billing_cycle' => 'monthly',
            'amount' => '499.00',
            'sender_name' => 'Ali Veli',
            'transfer_date' => now()->toDateString(),
        ], $attrs));
    }

    // ---------------------------------------------------------------- giriş ve yetki

    public function test_guest_is_redirected_to_login_and_login_page_renders(): void
    {
        $this->get('/yonetim')->assertRedirect(route('panel.login'));
        $this->get('/yonetim/havaleler')->assertRedirect(route('panel.login'));
        $this->get('/panel/giris')->assertOk()->assertSee('Panele giriş')
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false);
        $this->get('/panel/sifremi-unuttum')->assertOk();
    }

    public function test_admin_logs_in_and_lands_on_dashboard(): void
    {
        $admin = $this->makeAdmin();

        $this->post('/panel/giris', ['email' => strtoupper($admin->email), 'password' => 'yanlis'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/panel/giris', ['email' => $admin->email, 'password' => 'secret-password-123'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);

        $this->get('/yonetim')->assertOk()->assertSee('Genel bakış')->assertSee('Bekleyen havale')
            ->assertSee('Günlük işlem sayısı')->assertSee('Yeni müşteri kaydı')->assertSee('Tablo olarak göster')
            ->assertSee('İşletme grafikleri')->assertSee('Profesyonel paketli şube')
            ->assertSee('En çok işlem yapan şubeler')->assertSee('Mutfağa göre şubeler');
        $this->post('/panel/cikis')->assertRedirect(route('panel.login'));
        $this->assertGuest();
    }

    public function test_other_panel_users_cannot_open_admin_and_see_coming_soon(): void
    {
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);

        $this->post('/panel/giris', ['email' => $manager->email, 'password' => 'secret-password'])
            ->assertRedirect(route('panel.home'));
        $this->get('/panel')->assertRedirect(route('panel.tx.screen')); // şube yöneticisi işlem ekranına gider
        $this->get('/yonetim')->assertForbidden()
            ->assertSee('yalnızca platform yöneticisi içindir')
            ->assertSee($manager->email)->assertSee('şube yöneticisi')
            ->assertSee('Çıkış yap ve başka hesapla gir');
        $this->get('/yonetim/ayarlar')->assertForbidden();
    }

    public function test_passive_admin_cannot_log_in(): void
    {
        $admin = $this->makeAdmin();
        $admin->update(['status' => 'passive']);

        $this->post('/panel/giris', ['email' => $admin->email, 'password' => 'secret-password-123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_all_admin_pages_render(): void
    {
        $admin = $this->makeAdmin();
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        $customer = $this->makeCustomer();
        $payment = $this->makePayment($branch, $manager);
        $this->makeRateCampaign($branch, '15.00');

        $this->actingAs($admin);
        foreach ([
            '/yonetim', '/yonetim/isletmeler', '/yonetim/isletmeler?durum=pending', '/yonetim/isletmeler?q=test',
            '/yonetim/isletmeler/'.$branch->business_id, '/yonetim/musteriler', '/yonetim/musteriler?q='.$customer->customer_no,
            '/yonetim/musteriler/'.$customer->id, '/yonetim/havaleler', '/yonetim/havaleler?durum=all',
            '/yonetim/havaleler/'.$payment->id, '/yonetim/abonelikler', '/yonetim/abonelikler?durum=all',
            '/yonetim/sponsorluklar', '/yonetim/sponsorluklar?ay=2026-12', '/yonetim/kampanyalar', '/yonetim/kampanyalar?durum=all',
            '/yonetim/sikayetler', '/yonetim/sikayetler?durum=resolved', '/yonetim/kategoriler', '/yonetim/ayarlar',
            '/yonetim/denetim', '/yonetim/denetim?islem=panel.login&baslangic=2026-01-01', '/yonetim/denetim/numara-sorgulari?gun=7',
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    // ---------------------------------------------------------------- başvurular

    public function test_application_approval_and_rejection(): void
    {
        $admin = $this->makeAdmin();
        $a = $this->makeApplication();
        $b = $this->makeApplication();
        $this->actingAs($admin);

        $this->post(route('admin.businesses.approve', $a))->assertSessionHas('status');
        $a->refresh();
        $this->assertSame('approved', $a->status);
        $this->assertSame($admin->id, $a->approved_by);
        $this->assertNotNull($a->approved_at);
        $this->assertDatabaseHas('panel_notifications', ['panel_user_id' => $a->owner->id, 'type' => 'business_approved']);

        // İkinci onay: hata mesajı, durum değişmez
        $this->post(route('admin.businesses.approve', $a))->assertSessionHas('error');

        $this->post(route('admin.businesses.reject', $b), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post(route('admin.businesses.reject', $b), ['reason' => 'Vergi levhası eksik'])->assertSessionHas('status');
        $this->assertSame('rejected', $b->refresh()->status);
        $this->assertSame('Vergi levhası eksik', $b->rejection_reason);

        $this->assertTrue(AuditLog::query()->where('action', 'business.rejected')->where('subject_id', $b->id)->exists());
    }

    public function test_suspending_business_hides_branch_and_blocks_transactions(): void
    {
        $admin = $this->makeAdmin();
        $branch = $this->makeBranch();
        $this->actingAs($admin);

        $this->post(route('admin.businesses.status', $branch->business_id), ['status' => 'suspended', 'reason' => 'Şikâyet'])
            ->assertSessionHas('status');

        $branch->refresh()->load('business');
        $this->assertSame('suspended', $branch->business->status);
        $this->assertFalse($branch->isOperational());

        $this->post(route('admin.businesses.status', $branch->business_id), ['status' => 'approved']);
        $this->assertTrue($branch->refresh()->load('business')->isOperational());

        $this->post(route('admin.branches.status', $branch), ['status' => 'passive']);
        $this->assertSame('passive', $branch->refresh()->status);
    }

    // ---------------------------------------------------------------- havaleler

    public function test_subscription_payment_approval_creates_and_extends_pro(): void
    {
        $admin = $this->makeAdmin();
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        $this->actingAs($admin);

        $first = $this->makePayment($branch, $manager);
        $this->post(route('admin.payments.approve', $first))->assertSessionHas('status');

        $sub = Subscription::query()->where('payment_notification_id', $first->id)->firstOrFail();
        $this->assertSame('active', $sub->status);
        $this->assertSame('499.00', (string) $sub->price);
        $this->assertTrue($sub->starts_at->lte(now()));
        $this->assertSame('approved', $first->refresh()->status);
        $this->assertSame($admin->id, $first->reviewed_by);
        $this->assertDatabaseHas('panel_notifications', ['panel_user_id' => $manager->id, 'type' => 'payment_approved']);

        // Erken yenileme: yıllık dönem mevcut dönemin bitişinden başlar
        $second = $this->makePayment($branch, $manager, ['billing_cycle' => 'yearly', 'amount' => '4990.00']);
        $this->post(route('admin.payments.approve', $second))->assertSessionHas('status');
        $next = Subscription::query()->where('payment_notification_id', $second->id)->firstOrFail();
        $this->assertTrue($next->starts_at->equalTo($sub->ends_at));
        $this->assertTrue($next->ends_at->equalTo(CarbonImmutable::parse($sub->ends_at)->addYearNoOverflow()));

        // Aynı bildirim ikinci kez onaylanamaz
        $this->post(route('admin.payments.approve', $second))->assertSessionHas('error');

        // Gelir grafiği onaylanan havaleleri gösterir (tablo görünümünde)
        $this->get('/yonetim')->assertOk()->assertSee('5.489,00 TL', false); // 499 + 4.990 aynı ayda
        $this->assertSame(2, Subscription::query()->where('branch_id', $branch->id)->count());
    }

    public function test_sponsorship_payment_approval_and_duplicates(): void
    {
        $admin = $this->makeAdmin();
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        $this->actingAs($admin);

        $thisMonth = now()->startOfMonth()->toDateString();
        $nextMonth = now()->startOfMonth()->addMonthNoOverflow()->toDateString();

        $p1 = $this->makePayment($branch, $manager, ['purpose' => 'sponsorship', 'billing_cycle' => null, 'sponsorship_month' => $thisMonth, 'amount' => '750']);
        $this->post(route('admin.payments.approve', $p1))->assertSessionHas('status');
        $this->assertSame('active', Sponsorship::query()->where('branch_id', $branch->id)->where('month', $thisMonth)->value('status'));

        $p2 = $this->makePayment($branch, $manager, ['purpose' => 'sponsorship', 'billing_cycle' => null, 'sponsorship_month' => $nextMonth, 'amount' => '750']);
        $this->post(route('admin.payments.approve', $p2))->assertSessionHas('status');
        $this->assertSame('pending', Sponsorship::query()->where('branch_id', $branch->id)->where('month', $nextMonth)->value('status'));

        // Aynı ay için ikinci bildirim onaylanamaz, beklemede kalır
        $dup = $this->makePayment($branch, $manager, ['purpose' => 'sponsorship', 'billing_cycle' => null, 'sponsorship_month' => $thisMonth, 'amount' => '750']);
        $this->post(route('admin.payments.approve', $dup))->assertSessionHas('error');
        $this->assertSame('pending', $dup->refresh()->status);

        // Geçmiş ay onaylanamaz
        $past = $this->makePayment($branch, $manager, ['purpose' => 'sponsorship', 'billing_cycle' => null,
            'sponsorship_month' => now()->startOfMonth()->subMonthNoOverflow()->toDateString(), 'amount' => '750']);
        $this->post(route('admin.payments.approve', $past))->assertSessionHas('error');

        // Ret gerekçe ister
        $this->post(route('admin.payments.reject', $dup), ['note' => ''])->assertSessionHasErrors('note');
        $this->post(route('admin.payments.reject', $dup), ['note' => 'Mükerrer bildirim'])->assertSessionHas('status');
        $this->assertSame('rejected', $dup->refresh()->status);
        $this->assertDatabaseHas('panel_notifications', ['panel_user_id' => $manager->id, 'type' => 'payment_rejected']);

        // İptal edilen sponsorluk listelerde görünmez
        $s = Sponsorship::query()->where('branch_id', $branch->id)->where('month', $thisMonth)->firstOrFail();
        $this->post(route('admin.sponsorships.cancel', $s), ['reason' => 'Hatalı'])->assertSessionHas('status');
        $this->assertSame('cancelled', $s->refresh()->status);
    }

    public function test_subscription_cancel_returns_branch_to_free(): void
    {
        $admin = $this->makeAdmin();
        $branch = $this->makeBranch();
        $sub = $this->makePro($branch);
        $this->actingAs($admin);

        $this->post(route('admin.subscriptions.cancel', $sub), ['reason' => 'Yanlış şube'])->assertSessionHas('status');
        $this->assertSame('cancelled', $sub->refresh()->status);
        $this->assertFalse(app(\App\Domain\Billing\PlanResolver::class)->isPro($branch));
    }

    // ---------------------------------------------------------------- müşteriler

    public function test_customer_birth_date_correction_and_passivation(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $token = $customer->createToken('test')->plainTextToken;
        $this->actingAs($admin);

        $this->post(route('admin.customers.birth-date', $customer), ['birth_date' => '1991-02-03'])
            ->assertSessionHasErrors('reason');
        $this->post(route('admin.customers.birth-date', $customer), ['birth_date' => '1991-02-03', 'reason' => 'Kimlik gösterildi'])
            ->assertSessionHas('status');
        $this->assertSame('1991-02-03', $customer->refresh()->birth_date->toDateString());

        $log = AuditLog::query()->where('action', 'customer.birth_date_corrected')->where('subject_id', $customer->id)->firstOrFail();
        $this->assertSame('1990-05-15', $log->payload['from']);
        $this->assertSame('1991-02-03', $log->payload['to']);

        $this->post(route('admin.customers.status', $customer), ['status' => 'passive'])->assertSessionHas('status');
        $this->assertSame('passive', $customer->refresh()->status);
        $this->assertSame(0, $customer->tokens()->count());
        $this->assertNotEmpty($token);
    }

    public function test_customer_search_by_number_lists_exact_match_first(): void
    {
        $admin = $this->makeAdmin();
        $target = $this->makeCustomer();
        $no = (string) $target->customer_no;
        // Telefonunda aranan numara geçen başka bir müşteri
        $this->makeCustomer(['phone' => '0599'.str_pad($no, 7, '9', STR_PAD_LEFT)]);

        $html = $this->actingAs($admin)->get('/yonetim/musteriler?q='.$no)->assertOk()->getContent();
        preg_match('/<td class="n num">(\d+)<\/td>/', $html, $m);
        $this->assertSame($no, $m[1] ?? null);
    }

    // ---------------------------------------------------------------- moderasyon

    public function test_review_removal_recalculates_rating(): void
    {
        $admin = $this->makeAdmin();
        $branch = $this->makeBranch();
        $c1 = $this->makeCustomer();
        $c2 = $this->makeCustomer();

        $r1 = Review::query()->create(['branch_id' => $branch->id, 'customer_id' => $c1->id, 'score' => 10, 'comment' => 'Harika']);
        Review::query()->create(['branch_id' => $branch->id, 'customer_id' => $c2->id, 'score' => 6, 'comment' => 'İdare eder']);
        app(\App\Domain\Reviews\RatingAggregator::class)->refresh($branch);
        $this->assertSame('8.0', (string) $branch->refresh()->rating_avg);

        ReviewReport::query()->create(['review_id' => $r1->id, 'reporter_type' => 'customer', 'reporter_id' => $c2->id, 'reason' => 'Sahte']);

        $this->actingAs($admin);
        $this->get('/yonetim/sikayetler')->assertOk()->assertSee('Harika')->assertSee('Sahte');

        $this->post(route('admin.reviews.remove', $r1), ['reason' => 'Sahte yorum'])->assertSessionHas('status');
        $this->assertSame('removed', $r1->refresh()->status);
        $branch->refresh();
        $this->assertSame(1, $branch->rating_count);
        $this->assertSame('6.0', (string) $branch->rating_avg);
        $this->assertSame(0, ReviewReport::query()->where('review_id', $r1->id)->where('status', 'open')->count());
    }

    public function test_campaign_removal(): void
    {
        $admin = $this->makeAdmin();
        $branch = $this->makeBranch();
        $campaign = $this->makeRateCampaign($branch, '20.00');
        $this->actingAs($admin);

        $this->post(route('admin.campaigns.remove', $campaign), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post(route('admin.campaigns.remove', $campaign), ['reason' => 'Yanıltıcı'])->assertSessionHas('status');
        $this->assertSame('ended', $campaign->refresh()->status);
        $this->assertFalse(Campaign::query()->running()->whereKey($campaign->id)->exists());
    }

    // ---------------------------------------------------------------- ayarlar ve kategoriler

    public function test_settings_update(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin);

        $valid = [
            'free_monthly_customer_limit' => '150', 'warning_threshold_1' => '75', 'warning_threshold_2' => '95',
            'subscription_expiry_warning_days' => '10', 'pro_monthly_price' => '1.299,90', 'pro_yearly_price' => '12990',
            'bank_name' => 'Örnek Bank', 'iban' => 'TR12 0006 1005 1978 6457 8413 26', 'account_holder' => 'Kumbare Ltd.',
            'sponsorship_monthly_price' => '750,50', 'free_description' => 'Ücretsiz', 'pro_description' => 'Sınırsız',
        ];

        $this->post(route('admin.settings.update'), ['warning_threshold_2' => '50'] + $valid)->assertSessionHasErrors('warning_threshold_2');
        $this->post(route('admin.settings.update'), ['iban' => 'TR12'] + $valid)->assertSessionHasErrors('iban');

        $this->post(route('admin.settings.update'), $valid)->assertSessionHas('status');
        Setting::flushCache();
        $this->assertSame(150, Setting::getInt('free_monthly_customer_limit', 0));
        $this->assertSame('TR120006100519786457841326', Setting::get('iban'));
        $this->assertSame('750.50', Setting::get('sponsorship_monthly_price'));
        $pro = DB::table('plans')->where('code', 'pro')->first();
        $this->assertSame('1299.90', (string) $pro->monthly_price);
        $this->assertSame('12990.00', (string) $pro->yearly_price);
        $this->assertTrue(AuditLog::query()->where('action', 'settings.updated')->exists());
    }

    public function test_category_create_and_update(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin);
        $n = $this->next();

        $this->post(route('admin.categories.store'), ['name' => "Işık Mutfağı {$n}", 'sort_order' => 5, 'is_active' => 1])->assertSessionHas('status');
        $cat = \App\Models\Category::query()->where('name', "Işık Mutfağı {$n}")->firstOrFail();
        $this->assertSame("isik-mutfagi-{$n}", $cat->slug);

        $this->post(route('admin.categories.update', $cat), ['name' => $cat->name, 'slug' => $cat->slug, 'sort_order' => 1])->assertSessionHas('status');
        $this->assertFalse($cat->refresh()->is_active);

        $this->post(route('admin.categories.store'), ['name' => 'X', 'slug' => $cat->slug])->assertSessionHasErrors('slug');
    }

    public function test_robots_disallows_panel(): void
    {
        config(['app.web_domain' => null]);
        $this->get('/robots.txt')->assertSee('Disallow: /yonetim')->assertSee('Disallow: /panel');
    }
}
