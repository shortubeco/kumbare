<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Business;
use App\Models\District;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\PanelUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** İşletme (marka) yöneticisi paneli. */
class BusinessPanelTest extends TestCase
{
    private function makeOwner(Business $business): PanelUser
    {
        $n = $this->next();

        return PanelUser::query()->create([
            'role' => PanelUser::ROLE_BUSINESS_OWNER, 'business_id' => $business->id,
            'first_name' => 'İşletme', 'last_name' => "Sahibi{$n}", 'email' => "owner{$n}@test.local",
            'password' => 'secret-password', 'status' => 'active',
        ]);
    }

    public function test_owner_access_and_scoping(): void
    {
        $branch = $this->makeBranch('10.00');
        $owner = $this->makeOwner($branch->business);
        $this->earnAs($this->makeStaff($branch), $this->makeCustomer(), '1000.00');
        $otherBranch = $this->makeBranch(); // başka işletme

        $this->post('/panel/giris', ['email' => $owner->email, 'password' => 'secret-password'])->assertRedirect(route('panel.home'));
        $this->get('/panel')->assertRedirect(route('biz.overview'));
        $this->get('/panel/isletme')->assertOk()
            ->assertSee($branch->business->name)->assertSee($branch->name)->assertSee('100,00 TL', false)
            ->assertDontSee($otherBranch->business->name)->assertSee('İşletme paneli');
        foreach (['subeler', 'sube-yoneticileri', 'menu', 'raporlar', 'abonelikler', 'bilgiler', 'subeler/yeni', 'sube-yoneticileri/yeni'] as $p) {
            $this->get('/panel/isletme/'.$p)->assertOk();
        }
        $this->get('/panel/isletme/subeler/'.$branch->id)->assertOk()->assertSee('Şube yöneticisi');
        $this->get('/panel/isletme/subeler/'.$otherBranch->id)->assertNotFound();
        $this->get('/panel/islem')->assertForbidden();

        // Şube yöneticisi işletme paneline giremez; bekleyen başvuru sahibi bilgi sayfasına döner
        $this->actingAs($this->makeManager($branch), 'web')->get('/panel/isletme')->assertForbidden();
        $pending = Business::query()->create(['name' => 'Bekleyen', 'slug' => 'bekleyen-'.$this->next(), 'status' => 'pending']);
        $this->actingAs($this->makeOwner($pending), 'web')->get('/panel/isletme')->assertRedirect(route('panel.home'));
    }

    public function test_profile_update_with_logo(): void
    {
        Storage::fake('public');
        $branch = $this->makeBranch();
        $owner = $this->makeOwner($branch->business);
        $n = $this->next();
        $catId = \App\Models\Category::query()->create(['name' => "Mutfak {$n}", 'slug' => "mutfak-{$n}", 'is_active' => true])->id;
        $this->actingAs($owner, 'web');

        $this->post('/panel/isletme/bilgiler', ['name' => '', 'category_id' => $catId])->assertSessionHasErrors('name');
        $this->post('/panel/isletme/bilgiler', [
            'name' => 'Yeni Marka Adı', 'category_id' => $catId, 'description' => 'Açıklama',
            'logo' => UploadedFile::fake()->image('logo.png', 800, 800),
        ])->assertSessionHas('status');
        $b = $branch->business->refresh();
        $this->assertSame('Yeni Marka Adı', $b->name);
        Storage::disk('public')->assertExists($b->logo_path);
    }

    public function test_branch_create_status_and_delete_rules(): void
    {
        $branch = $this->makeBranch();
        $owner = $this->makeOwner($branch->business);
        $kadikoy = $this->kadikoy();
        $otherDistrict = District::query()->where('city_id', '<>', $kadikoy->city_id)->first();
        $this->actingAs($owner, 'web');

        $this->getJson('/panel/isletme/ilceler/'.$kadikoy->city_id)->assertOk()->assertJsonFragment(['name' => $kadikoy->name]);

        $form = ['city_id' => $kadikoy->city_id, 'district_id' => $kadikoy->id, 'name' => 'Moda', 'address' => 'Moda Cad. 1', 'phone' => '0216 111 22 33'];
        $this->post('/panel/isletme/subeler', ['district_id' => $otherDistrict->id] + $form)->assertSessionHasErrors('district_id');

        $this->post('/panel/isletme/subeler', $form)->assertRedirect();
        $new = Branch::query()->where('business_id', $branch->business_id)->where('name', 'Moda')->firstOrFail();
        $this->assertSame('istanbul-kadikoy', $new->slug);
        $this->assertSame('0.00', (string) $new->reward_rate);
        $this->assertSame('02161112233', $new->phone);

        $this->post('/panel/isletme/subeler', ['name' => 'Moda 2'] + $form);
        $this->assertSame('istanbul-kadikoy-2', Branch::query()->where('name', 'Moda 2')->value('slug'));

        // Pasif / aktif
        $this->post("/panel/isletme/subeler/{$new->id}/durum", ['status' => 'passive'])->assertSessionHas('status');
        $this->assertSame('passive', $new->refresh()->status);
        $this->post("/panel/isletme/subeler/{$new->id}/durum", ['status' => 'active']);

        // İşlem görmüş şube silinemez
        $this->earnAs($this->makeStaff($branch), $this->makeCustomer(), '100.00');
        $this->actingAs($owner, 'web')->post("/panel/isletme/subeler/{$branch->id}/sil")->assertSessionHas('error');
        $this->assertSame('active', $branch->refresh()->status);

        // İşlemsiz şube silinir, panel hesapları kapanır
        $m = $this->makeManager($new);
        $this->actingAs($owner, 'web')->post("/panel/isletme/subeler/{$new->id}/sil")->assertRedirect(route('biz.branches'));
        $this->assertSame('deleted', $new->refresh()->status);
        $this->assertSame('passive', $m->refresh()->status);
        $this->get('/panel/isletme/subeler/'.$new->id)->assertNotFound();
    }

    public function test_branch_manager_accounts_one_active_per_branch(): void
    {
        $branch = $this->makeBranch();
        $owner = $this->makeOwner($branch->business);
        $this->actingAs($owner, 'web');
        $n = $this->next();
        $form = fn (array $o = []) => array_merge([
            'branch_id' => $branch->id, 'first_name' => 'Ayşe', 'last_name' => 'Yönetici', 'email' => "yonetici{$n}@test.local",
            'password' => 'gizli-sifre-123', 'password_confirmation' => 'gizli-sifre-123',
        ], $o);

        $this->post('/panel/isletme/sube-yoneticileri', $form())->assertRedirect(route('biz.managers'));
        $first = PanelUser::query()->where('email', "yonetici{$n}@test.local")->firstOrFail();
        $this->assertSame(PanelUser::ROLE_BRANCH_MANAGER, $first->role);
        $this->assertSame($branch->id, $first->branch_id);

        // İkinci aktif yönetici eklenemez
        $this->post('/panel/isletme/sube-yoneticileri', $form(['email' => "ikinci{$n}@test.local"]))->assertSessionHas('error');

        // Yönetici giriş yapabilir
        auth()->logout();
        $this->post('/panel/giris', ['email' => $first->email, 'password' => 'gizli-sifre-123'])->assertRedirect(route('panel.home'));
        auth()->logout();

        // Pasifleştir → yenisini ekle → eskisi aktif edilemez
        $this->actingAs($owner, 'web')->post("/panel/isletme/sube-yoneticileri/{$first->id}/durum")->assertSessionHas('status');
        $this->assertSame('passive', $first->refresh()->status);
        $this->post('/panel/isletme/sube-yoneticileri', $form(['email' => "ikinci{$n}@test.local"]))->assertRedirect(route('biz.managers'));
        $this->post("/panel/isletme/sube-yoneticileri/{$first->id}/durum")->assertSessionHas('error');

        // Şifre değiştirme
        $this->post("/panel/isletme/sube-yoneticileri/{$first->id}", [
            'first_name' => 'Ayşe', 'last_name' => 'Yönetici', 'email' => $first->email,
            'password' => 'yeni-sifre-456', 'password_confirmation' => 'yeni-sifre-456',
        ])->assertRedirect(route('biz.managers'));
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('yeni-sifre-456', $first->refresh()->password));

        // Başka işletmenin yöneticisi düzenlenemez
        $foreign = $this->makeManager($this->makeBranch());
        $this->get("/panel/isletme/sube-yoneticileri/{$foreign->id}/duzenle")->assertNotFound();
    }

    public function test_menu_management_and_site(): void
    {
        $branch = $this->makeBranch();
        $owner = $this->makeOwner($branch->business);
        $this->actingAs($owner, 'web');

        $this->post('/panel/isletme/menu/kategoriler', ['name' => 'Ana yemekler']);
        $this->post('/panel/isletme/menu/kategoriler', ['name' => 'Tatlılar']);
        [$main, $dessert] = MenuCategory::query()->where('business_id', $branch->business_id)->orderBy('sort_order')->get()->all();

        $this->post('/panel/isletme/menu/urunler', ['menu_category_id' => $main->id, 'name' => 'Adana', 'price' => 'abc'])->assertSessionHasErrors('price');
        $this->post('/panel/isletme/menu/urunler', ['menu_category_id' => $main->id, 'name' => 'Adana Kebap', 'price' => '1.234,50', 'is_available' => '1']);
        $this->post('/panel/isletme/menu/urunler', ['menu_category_id' => $main->id, 'name' => 'Urfa Kebap', 'price' => '300', 'is_available' => '0']);
        $adana = MenuItem::query()->where('name', 'Adana Kebap')->firstOrFail();
        $urfa = MenuItem::query()->where('name', 'Urfa Kebap')->firstOrFail();
        $this->assertSame('1234.50', (string) $adana->price);
        $this->assertFalse($urfa->is_available);

        // Sıralama: Urfa yukarı
        $this->post("/panel/isletme/menu/urunler/{$urfa->id}/tasi", ['yon' => 'yukari']);
        $this->assertSame([$urfa->id, $adana->id], MenuItem::query()->where('menu_category_id', $main->id)->orderBy('sort_order')->pluck('id')->all());
        // Kategori sırası: Tatlılar yukarı
        $this->post("/panel/isletme/menu/kategoriler/{$dessert->id}/tasi", ['yon' => 'yukari']);
        $this->assertSame($dessert->id, MenuCategory::query()->where('business_id', $branch->business_id)->orderBy('sort_order')->value('id'));

        // Sitede menü
        config(['app.web_domain' => null]);
        $this->get(route('web.branch', [$branch->business->slug, $branch->slug]))->assertOk()
            ->assertSee('Adana Kebap')->assertSee('1.234,50', false)->assertSee('Urfa Kebap');

        // Satışa aç, sil, kategori silme kuralı
        $this->post("/panel/isletme/menu/urunler/{$urfa->id}/satis");
        $this->assertTrue($urfa->refresh()->is_available);
        $this->post("/panel/isletme/menu/kategoriler/{$main->id}/sil")->assertSessionHas('error');
        $this->post("/panel/isletme/menu/urunler/{$adana->id}/sil");
        $this->assertSoftDeleted('menu_items', ['id' => $adana->id]);
        $this->post("/panel/isletme/menu/kategoriler/{$dessert->id}/sil")->assertSessionHas('status');
        $this->assertDatabaseMissing('menu_categories', ['id' => $dessert->id]);
        // Ürünü silinmiş (yumuşak) kategori de silinebilir
        $this->post("/panel/isletme/menu/urunler/{$urfa->id}/sil");
        $this->post("/panel/isletme/menu/kategoriler/{$main->id}/sil")->assertSessionHas('status');
        $this->assertDatabaseMissing('menu_categories', ['id' => $main->id]);
        $this->assertDatabaseMissing('menu_items', ['id' => $adana->id]);

    }

    public function test_reports_compare_and_csv(): void
    {
        $branch = $this->makeBranch('10.00');
        $second = $this->makeBranch('5.00', [], $branch->business);
        $owner = $this->makeOwner($branch->business);
        $customer = $this->makeCustomer();
        $this->earnAs($this->makeStaff($branch), $customer, '1000.00');   // 100
        $this->earnAs($this->makeStaff($second), $customer, '1000.00');   // 50
        $this->actingAs($owner, 'web');

        $this->get('/panel/isletme/raporlar')->assertOk()->assertSee('150,00', false)->assertSee($second->name);
        $body = $this->get('/panel/isletme/raporlar?indir=subeler')->assertOk()->streamedContent();
        $this->assertStringContainsString('TOPLAM;2;1;2;2000,00;150,00;0,00;150,00', $body); // aynı müşteri iki şubede: farklı müşteri 1
        $this->get('/panel/isletme/abonelikler')->assertOk()->assertSee('Ücretsiz');
    }
}
