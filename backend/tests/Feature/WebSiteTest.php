<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\PanelUser;
use App\Models\Review;
use App\Support\TurkishText;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WebSiteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_home_page(): void
    {
        $this->makeBranch('15.00');

        $this->get('/')
            ->assertOk()
            ->assertSee('<title>Kumbare – Restoranda öde, ödülün kumbarana düşsün</title>', false)
            ->assertSee('rel="canonical"', false)
            ->assertSee('"@type":"WebSite"', false)
            ->assertSee('Nasıl çalışır?');
    }

    public function test_city_and_district_listing(): void
    {
        $branch = $this->makeBranch('12.50');
        $name = $branch->business->name;

        $this->get('/restoranlar/istanbul')
            ->assertOk()
            ->assertSee("İstanbul'daki restoranlar", false)
            ->assertSee($name)
            ->assertSee('%12,5')
            ->assertSee('<meta name="robots" content="index,follow">', false);

        $this->get('/restoranlar/istanbul/kadikoy')
            ->assertOk()
            ->assertSee("Kadıköy'deki restoranlar", false)
            ->assertSee($name);

        $this->get('/restoranlar/istanbul?min_odul=10')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,follow">', false);

        $this->get('/restoranlar/olmayan-il')->assertNotFound();
    }

    public function test_listing_hides_unapproved_and_shows_no_reward_label(): void
    {
        $hidden = $this->makeBranch('10.00');
        $hidden->business->update(['status' => 'pending']);
        $zero = $this->makeBranch('0.00');

        $this->get('/restoranlar/istanbul/kadikoy?siralama=yeni')
            ->assertOk()
            ->assertDontSee($hidden->business->name)
            ->assertSee($zero->business->name)
            ->assertSee('Ödül yok');
    }

    public function test_branch_detail_page_with_structured_data(): void
    {
        $branch = $this->makeBranch('10.00');
        $customer = $this->makeCustomer();
        $this->earnAs($this->makeStaff($branch), $customer, '100')->assertCreated();
        Review::query()->create(['branch_id' => $branch->id, 'customer_id' => $customer->id, 'score' => 9, 'comment' => 'Çok güzel', 'status' => 'published']);
        app(\App\Domain\Reviews\RatingAggregator::class)->refresh($branch);

        $this->get("/restoran/{$branch->business->slug}/{$branch->slug}")
            ->assertOk()
            ->assertSee('"@type":"Restaurant"', false)
            ->assertSee('"aggregateRating"', false)
            ->assertSee('"bestRating":10', false)
            ->assertSee('9.0')
            ->assertSee('/ 10 — 1 değerlendirme', false)
            ->assertSee('Çok güzel')
            ->assertSee('Ahmet Y.')
            ->assertDontSee('Yılmaz')     // müşterinin soyadı açık yazılmaz
            ->assertDontSee('@test.local'); // e-posta sızmaz

        $this->get("/restoran/{$branch->business->slug}/olmayan-sube")->assertNotFound();
    }

    public function test_business_application_creates_pending_business(): void
    {
        $category = \App\Models\Category::query()->create(['name' => 'Test Mutfak', 'slug' => 'test-mutfak-'.$this->next(), 'is_active' => true]);

        $this->post('/isletmeler-icin/basvuru', [
            'business_name' => 'Deneme Lokantası',
            'category_id' => $category->id,
            'legal_name' => 'Deneme Gıda Ltd. Şti.',
            'tax_office' => 'Kadıköy',
            'tax_number' => '1234567890',
            'first_name' => 'Ayşe',
            'last_name' => 'Kaya',
            'email' => 'AYSE.basvuru@test.local',
            'phone' => '0532 000 00 00',
            'password' => 'guclu-sifre-123',
            'password_confirmation' => 'guclu-sifre-123',
            'kvkk' => '1',
        ])->assertRedirect(route('web.business.thanks'));

        $business = Business::query()->where('name', 'Deneme Lokantası')->firstOrFail();
        $this->assertSame('pending', $business->status);
        $owner = PanelUser::query()->where('business_id', $business->id)->firstOrFail();
        $this->assertSame('ayse.basvuru@test.local', $owner->email);
        $this->assertSame(PanelUser::ROLE_BUSINESS_OWNER, $owner->role);

        // Onaylanmamış işletme sitede görünmez.
        $this->get('/restoranlar?q=Deneme')->assertDontSee('Deneme Lokantası');
    }

    public function test_business_application_validation_and_honeypot(): void
    {
        $this->post('/isletmeler-icin/basvuru', ['business_name' => ''])
            ->assertSessionHasErrors(['business_name', 'category_id', 'tax_number', 'kvkk']);

        $before = Business::query()->count();
        $this->post('/isletmeler-icin/basvuru', ['kb_bos' => 'spam', 'email' => 'bot@example.com'])->assertRedirect(route('web.business.thanks'));
        $this->assertSame($before, Business::query()->count());
        $this->assertTrue(\App\Models\AuditLog::query()->where('action', 'business.application_blocked')->whereRaw("payload->>'email' = ?", ['bot@example.com'])->exists());
    }

    public function test_password_reset_page(): void
    {
        $this->get('/sifre-sifirla?token=abc&email=a@b.c')->assertOk()->assertSee('Yeni şifre belirle');
        $this->get('/sifre-sifirla')->assertOk()->assertSee('Bu bağlantı eksik');

        $this->post('/sifre-sifirla?token=abc&email=a@b.c', [
            'token' => 'gecersiz', 'email' => 'a@b.c', 'password' => 'yeni-sifre-1', 'password_confirmation' => 'yeni-sifre-1',
        ])->assertSessionHasErrors('token');
    }

    public function test_robots_sitemap_and_404(): void
    {
        $branch = $this->makeBranch();

        $this->get('/robots.txt')->assertOk()->assertSee('Sitemap: http')->assertSee('Disallow: /api/');
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('/restoran/'.$branch->business->slug.'/'.$branch->slug, false)
            ->assertSee('/restoranlar/istanbul/kadikoy', false);

        $this->get('/olmayan-sayfa')->assertNotFound()->assertSee('Bu sayfa bulunamadı');
    }

    public function test_turkish_locative_suffix(): void
    {
        $this->assertSame("İstanbul'da", TurkishText::locative('İstanbul'));
        $this->assertSame("Kadıköy'de", TurkishText::locative('Kadıköy'));
        $this->assertSame("Muş'ta", TurkishText::locative('Muş'));
        $this->assertSame("Iğdır'da", TurkishText::locative('Iğdır'));
        $this->assertSame("Tokat'ta", TurkishText::locative('Tokat'));
        $this->assertSame("İzmir'deki", TurkishText::locativeAdjective('İzmir'));
    }
}
