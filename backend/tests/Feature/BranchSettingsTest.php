<?php

namespace Tests\Feature;

use App\Domain\Loyalty\EligibleCampaignResolver;
use App\Domain\Notifications\PanelNotifier;
use App\Models\AuditLog;
use App\Models\BranchHour;
use App\Models\BranchPhoto;
use App\Models\Campaign;
use App\Models\PanelNotification;
use App\Models\PanelUser;
use App\Models\Review;
use App\Models\ReviewReport;
use App\Models\RewardRateHistory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Şube ayarları: ödül oranı, şube bilgileri/fotoğraflar, kampanyalar, çalışanlar, değerlendirmeler, bildirimler. */
class BranchSettingsTest extends TestCase
{
    public function test_reward_rate_update_with_history(): void
    {
        $branch = $this->makeBranch('10.00');
        $manager = $this->makeManager($branch);
        $this->actingAs($manager, 'web');

        $this->get('/panel/odul-orani')->assertOk()->assertSee('Ödül oranı');
        $this->post('/panel/odul-orani', ['reward_rate' => '150'])->assertSessionHasErrors('reward_rate');
        $this->post('/panel/odul-orani', ['reward_rate' => '7,5', 'reward_terms' => 'Paket serviste geçmez.'])->assertSessionHas('status');

        $branch->refresh();
        $this->assertSame('7.50', (string) $branch->reward_rate);
        $this->assertSame('Paket serviste geçmez.', $branch->reward_terms);
        $h = RewardRateHistory::query()->where('branch_id', $branch->id)->firstOrFail();
        $this->assertSame('10.00', (string) $h->old_rate);
        $this->assertSame('7.50', (string) $h->new_rate);
        $this->get('/panel/odul-orani')->assertSee('%7,5');

        // Çalışan giremez
        $this->actingAs($this->makeStaff($branch), 'web')->get('/panel/odul-orani')->assertForbidden();
    }

    public function test_branch_info_hours_and_photos(): void
    {
        Storage::fake('public');
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        $this->actingAs($manager, 'web');
        $this->get('/panel/sube-bilgileri')->assertOk()->assertSee('Çalışma saatleri');

        $base = ['address' => 'Moda Cad. No: 1, Kadıköy', 'phone' => '0216 123 45 67', 'email' => 'sube@test.local'];
        $this->post('/panel/sube-bilgileri', $base + ['latitude' => '10', 'longitude' => '28'])->assertSessionHasErrors('latitude');
        $this->post('/panel/sube-bilgileri', $base + ['hours' => [1 => ['opens' => '09:00']]])->assertSessionHasErrors('hours.1.opens');

        $this->post('/panel/sube-bilgileri', $base + [
            'latitude' => '40,9870', 'longitude' => '29.0260',
            'hours' => [1 => ['opens' => '09:00', 'closes' => '23:00'], 2 => ['opens' => '18:00', 'closes' => '01:00'], 7 => ['closed' => '1']],
        ])->assertSessionHas('status');

        $branch->refresh();
        $this->assertSame('02161234567', $branch->phone);
        $this->assertSame('40.9870000', (string) $branch->latitude);
        $this->assertSame(3, BranchHour::query()->where('branch_id', $branch->id)->count());
        $this->assertTrue(BranchHour::query()->where('branch_id', $branch->id)->where('weekday', 7)->value('is_closed'));

        // Fotoğraflar
        $this->post('/panel/sube-bilgileri/fotograflar', ['photos' => [UploadedFile::fake()->create('belge.pdf', 10, 'application/pdf')]])
            ->assertSessionHasErrors('photos.0');
        $this->post('/panel/sube-bilgileri/fotograflar', ['photos' => [
            UploadedFile::fake()->image('a.jpg', 2400, 1800), UploadedFile::fake()->image('b.png', 800, 600),
        ]])->assertSessionHas('status');
        $photos = BranchPhoto::query()->where('branch_id', $branch->id)->orderBy('sort_order')->get();
        $this->assertCount(2, $photos);
        Storage::disk('public')->assertExists($photos[0]->path);
        $this->assertStringStartsWith('subeler/'.$branch->id.'/', $photos[0]->path);

        // Kapak değiştir, sil
        $this->post("/panel/sube-bilgileri/fotograflar/{$photos[1]->id}/kapak")->assertSessionHas('status');
        $this->assertSame($photos[1]->id, BranchPhoto::query()->where('branch_id', $branch->id)->orderBy('sort_order')->value('id'));
        $this->post("/panel/sube-bilgileri/fotograflar/{$photos[0]->id}/sil")->assertSessionHas('status');
        Storage::disk('public')->assertMissing($photos[0]->path);

        // Sitedeki şube sayfası kendi fotoğrafını kullanır
        config(['app.web_domain' => null]);
        $this->get(route('web.branch', [$branch->business->slug, $branch->slug]))->assertOk()
            ->assertSee($photos[1]->path, false)->assertDontSee('Temsilî görsel</span>', false);

        // Başka şubenin fotoğrafı silinemez
        $other = $this->makeManager($this->makeBranch());
        $this->actingAs($other, 'web')->post("/panel/sube-bilgileri/fotograflar/{$photos[1]->id}/sil")->assertNotFound();
    }

    public function test_campaign_create_edit_end_and_eligibility(): void
    {
        Storage::fake('public');
        $branch = $this->makeBranch('10.00');
        $manager = $this->makeManager($branch);
        $this->actingAs($manager, 'web');
        $today = now()->toDateString();
        $form = [
            'type' => 'rate_override', 'title' => 'Hafta içi %15', 'reward_rate' => '15',
            'starts_on' => $today, 'ends_on' => now()->addDays(10)->toDateString(), 'publish' => 'active',
        ];

        $this->post('/panel/kampanyalar', ['reward_rate' => ''] + $form)->assertSessionHasErrors('reward_rate');
        $this->post('/panel/kampanyalar', ['starts_on' => now()->subDay()->toDateString()] + $form)->assertSessionHasErrors('starts_on');
        $this->post('/panel/kampanyalar', ['ends_on' => now()->addYears(2)->toDateString()] + $form)->assertSessionHasErrors('ends_on');

        $this->post('/panel/kampanyalar', $form + ['image' => UploadedFile::fake()->image('k.jpg', 900, 600)])
            ->assertRedirect(route('panel.campaigns', ['durum' => 'yayinda']));
        $c = Campaign::query()->where('branch_id', $branch->id)->firstOrFail();
        $this->assertSame('active', $c->status);
        $this->assertSame('15.00', (string) $c->reward_rate);
        $this->assertTrue($c->ends_at->equalTo(now()->addDays(11)->startOfDay()));
        Storage::disk('public')->assertExists($c->image_path);

        // İşlem ekranında seçilebilir
        $customer = $this->makeCustomer();
        $this->assertTrue(app(EligibleCampaignResolver::class)->forCustomer($branch, $customer)->contains('id', $c->id));

        // Düzenle → taslak yap → artık seçilemez
        $this->get("/panel/kampanyalar/{$c->id}/duzenle")->assertOk()->assertSee('Hafta içi %15');
        $this->post("/panel/kampanyalar/{$c->id}", ['publish' => 'draft', 'title' => 'Yeni başlık', 'weekdays' => [1, 2, 3, 4, 5]] + $form)
            ->assertRedirect(route('panel.campaigns', ['durum' => 'taslak']));
        $c->refresh();
        $this->assertSame('draft', $c->status);
        $this->assertSame([1, 2, 3, 4, 5], $c->weekdayList());
        $this->assertFalse(app(EligibleCampaignResolver::class)->forCustomer($branch, $customer)->contains('id', $c->id));

        // Duyuru
        $this->post('/panel/kampanyalar', ['type' => 'announcement', 'reward_rate' => '', 'title' => 'Yeni menü'] + $form)->assertSessionHas('status');
        $this->assertNull(Campaign::query()->where('title', 'Yeni menü')->value('reward_rate'));

        // Sona erdir → düzenlenemez
        $this->post("/panel/kampanyalar/{$c->id}/bitir")->assertSessionHas('status');
        $this->assertSame('ended', $c->refresh()->status);
        $this->get("/panel/kampanyalar/{$c->id}/duzenle")->assertRedirect(route('panel.campaigns', ['durum' => 'biten']));
        $this->get('/panel/kampanyalar?durum=biten')->assertOk()->assertSee('Yeni başlık');

        // Başka şube göremez
        $this->actingAs($this->makeManager($this->makeBranch()), 'web')->get("/panel/kampanyalar/{$c->id}/duzenle")->assertNotFound();
    }

    public function test_staff_management_and_permissions(): void
    {
        $branch = $this->makeBranch('10.00');
        $manager = $this->makeManager($branch);
        $this->actingAs($manager, 'web');
        $n = $this->next();
        $email = "kasa{$n}@test.local";

        $this->post('/panel/calisanlar', [
            'first_name' => 'Kasa', 'last_name' => 'Görevlisi', 'email' => strtoupper($email),
            'password' => 'kisa', 'password_confirmation' => 'kisa', 'permissions' => ['earn'],
        ])->assertSessionHasErrors('password');

        $this->post('/panel/calisanlar', [
            'first_name' => 'Kasa', 'last_name' => 'Görevlisi', 'email' => strtoupper($email),
            'password' => 'gizli-sifre-123', 'password_confirmation' => 'gizli-sifre-123',
            'permissions' => ['lookup_customer', 'earn', 'bogus'],
        ])->assertSessionHasErrors('permissions.2');

        $this->post('/panel/calisanlar', [
            'first_name' => 'Kasa', 'last_name' => 'Görevlisi', 'email' => strtoupper($email),
            'password' => 'gizli-sifre-123', 'password_confirmation' => 'gizli-sifre-123',
            'permissions' => ['lookup_customer', 'earn'],
        ])->assertRedirect(route('panel.staff'));

        $staff = PanelUser::query()->where('email', $email)->firstOrFail();
        $this->assertSame(PanelUser::ROLE_STAFF, $staff->role);
        $this->assertSame($branch->id, $staff->branch_id);
        $this->assertTrue($staff->hasPermission('earn'));
        $this->assertFalse($staff->hasPermission('redeem'));

        // Aynı e-posta ikinci kez eklenemez
        $this->post('/panel/calisanlar', [
            'first_name' => 'X', 'last_name' => 'Y', 'email' => $email,
            'password' => 'gizli-sifre-123', 'password_confirmation' => 'gizli-sifre-123',
        ])->assertSessionHasErrors('email');

        // Çalışan giriş yapabilir
        auth()->logout();
        $this->post('/panel/giris', ['email' => $email, 'password' => 'gizli-sifre-123'])->assertRedirect(route('panel.home'));
        $this->get('/panel/islem')->assertOk()->assertSee('Ödül kazandır');
        auth()->logout();

        // Yetkiyi değiştir: kazandırma kaldırılır, kullandırma eklenir
        $this->actingAs($manager, 'web')->post("/panel/calisanlar/{$staff->id}", [
            'first_name' => 'Kasa', 'last_name' => 'Görevlisi', 'email' => $email, 'permissions' => ['lookup_customer', 'redeem'],
        ])->assertRedirect(route('panel.staff'));
        $staff = $staff->fresh('permissions');
        $this->assertFalse($staff->hasPermission('earn'));
        $this->assertTrue($staff->hasPermission('redeem'));
        $this->assertTrue(AuditLog::query()->where('action', 'staff.updated')->where('subject_id', $staff->id)->exists());

        // Pasifleştir → giriş yapamaz
        $this->post("/panel/calisanlar/{$staff->id}/durum")->assertSessionHas('status');
        $this->assertSame('passive', $staff->refresh()->status);
        auth()->logout();
        $this->post('/panel/giris', ['email' => $email, 'password' => 'gizli-sifre-123'])->assertSessionHasErrors('email');

        // Başka şubenin yöneticisi bu çalışanı düzenleyemez
        $this->actingAs($this->makeManager($this->makeBranch()), 'web')->get("/panel/calisanlar/{$staff->id}/duzenle")->assertNotFound();
    }

    public function test_reviews_reply_and_report(): void
    {
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        $customer = $this->makeCustomer(['first_name' => 'Elif', 'last_name' => 'Kaya']);
        $review = Review::query()->create(['branch_id' => $branch->id, 'customer_id' => $customer->id, 'score' => 4, 'comment' => 'Servis yavaştı']);
        $this->actingAs($manager, 'web');

        $this->get('/panel/degerlendirmeler')->assertOk()->assertSee('Servis yavaştı')->assertSee('Elif K.')->assertDontSee('Elif Kaya');
        $this->get('/panel/degerlendirmeler?filtre=dusuk')->assertSee('Servis yavaştı');

        $this->post("/panel/degerlendirmeler/{$review->id}/cevap", ['body' => ''])->assertSessionHasErrors('body');
        $this->post("/panel/degerlendirmeler/{$review->id}/cevap", ['body' => 'Geri bildiriminiz için teşekkürler.'])->assertSessionHas('status');
        $this->post("/panel/degerlendirmeler/{$review->id}/cevap", ['body' => 'Teşekkürler, ekibimizle konuştuk.'])->assertSessionHas('status');
        $this->assertSame('Teşekkürler, ekibimizle konuştuk.', $review->reply()->value('body'));
        $this->get('/panel/degerlendirmeler?filtre=cevapsiz')->assertDontSee('Servis yavaştı');

        $this->post("/panel/degerlendirmeler/{$review->id}/sikayet", ['reason' => 'Hakaret içeriyor'])->assertSessionHas('status');
        $this->assertTrue(ReviewReport::query()->where('review_id', $review->id)->where('reporter_type', 'panel_user')->where('reporter_id', $manager->id)->exists());
        $this->get('/panel/degerlendirmeler')->assertSee('Şikâyetiniz inceleniyor');

        // Başka şubenin yöneticisi cevap yazamaz
        $this->actingAs($this->makeManager($this->makeBranch()), 'web')
            ->post("/panel/degerlendirmeler/{$review->id}/cevap", ['body' => 'X'])->assertNotFound();
    }

    public function test_notifications_page_and_badge(): void
    {
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        $notifier = app(PanelNotifier::class);
        $notifier->notify($manager, 'payment_rejected', ['purpose' => 'subscription', 'note' => 'Havale bulunamadı']);
        $notifier->notify($manager, 'limit_reached', ['unique_customers' => 100, 'limit' => 100]);
        $this->actingAs($manager, 'web');

        $this->get('/panel/islem')->assertOk()->assertSee('aria-label="2 yeni"', false);
        $this->get('/panel/bildirimler')->assertOk()
            ->assertSee('Profesyonel paket havalesi onaylanmadı')->assertSee('Havale bulunamadı')
            ->assertSee('Aylık müşteri limiti doldu');
        $this->assertSame(0, PanelNotification::query()->where('panel_user_id', $manager->id)->whereNull('read_at')->count());
        $this->get('/panel/islem')->assertDontSee('aria-label="2 yeni"', false);

        // Çalışan da kendi bildirimlerini görür
        $staff = $this->makeStaff($branch);
        $notifier->notify($staff, 'approval_approved', ['request_type' => 'redeem', 'amount' => '40.00']);
        $this->actingAs($staff, 'web')->get('/panel/bildirimler')->assertOk()
            ->assertSee('Ödül kullanım isteği müşteri tarafından onaylandı')->assertSee('40,00 TL', false)
            ->assertDontSee('Havale bulunamadı');
    }
}
