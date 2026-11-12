<?php

namespace Tests\Feature;

use App\Domain\Loyalty\ApprovalService;
use App\Models\AuditLog;
use App\Models\PanelUser;
use App\Models\WalletTransaction;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Web panelindeki kasa işlem ekranı (oturum tabanlı, personel API'siyle aynı iş kuralları). */
class TransactionScreenTest extends TestCase
{
    public function test_staff_lands_on_transaction_screen_with_permission_based_buttons(): void
    {
        $branch = $this->makeBranch('10.00');
        $manager = $this->makeManager($branch);

        $this->post('/panel/giris', ['email' => $manager->email, 'password' => 'secret-password'])->assertRedirect(route('panel.home'));
        $this->get('/panel')->assertRedirect(route('panel.tx.screen'));
        $this->get('/panel/islem')->assertOk()
            ->assertSee('İşlem ekranı')->assertSee('Ödül kazandır')->assertSee('Ödül kullandır')
            ->assertSee('Paket ve ödeme')->assertSee('%10');

        // Yalnızca kazandırma yetkili çalışan: kullandır düğmesi ve ödeme menüsü yok
        $earnOnly = $this->makeStaff($branch, [PanelUser::PERM_LOOKUP, PanelUser::PERM_EARN]);
        $this->actingAs($earnOnly)->get('/panel/islem')->assertOk()
            ->assertSee('Ödül kazandır')->assertDontSee('id="tx-go-redeem"', false)->assertDontSee('Paket ve ödeme');
        $this->get('/panel/paket')->assertForbidden();

        // Hiç yetkisi olmayan çalışan
        $none = $this->makeStaff($branch, []);
        $this->actingAs($none)->get('/panel/islem')->assertOk()->assertSee('yetkisi verilmemiş');
    }

    public function test_guest_json_requests_get_401_and_page_redirects(): void
    {
        $this->getJson('/panel/islem/musteri/1')->assertStatus(401)->assertJsonPath('code', 'unauthenticated');
        $this->get('/panel/islem')->assertRedirect(route('panel.login'));
    }

    public function test_lookup_earn_with_idempotency(): void
    {
        $branch = $this->makeBranch('10.00');
        $manager = $this->makeManager($branch);
        $customer = $this->makeCustomer(['birth_date' => now()->subYears(30)->toDateString()]);
        $this->actingAs($manager);

        $this->getJson('/panel/islem/musteri/'.$customer->customer_no)->assertOk()
            ->assertJsonPath('data.customer.full_name', $customer->fullName())
            ->assertJsonPath('data.customer.is_birthday_today', true)
            ->assertJsonPath('data.wallet.balance', '0.00')
            ->assertJsonMissingPath('data.customer.birth_date');
        $this->assertTrue(AuditLog::query()->where('action', 'customer.lookup')->where('actor_id', $manager->id)->exists());

        $this->getJson('/panel/islem/musteri/99999999')->assertNotFound()->assertJsonPath('message', 'Müşteri bulunamadı.');

        $this->getJson('/panel/islem/musteri/'.$customer->customer_no.'/kampanyalar')->assertOk()->assertJsonPath('default_rate', '10.00');

        $this->postJson('/panel/islem/kazandir/ozet', ['customer_no' => $customer->customer_no, 'spend_amount' => '1234.50'])
            ->assertOk()->assertJsonPath('data.reward_amount', '123.45')->assertJsonPath('data.can_earn', true);

        $key = (string) Str::uuid();
        $body = ['customer_no' => $customer->customer_no, 'spend_amount' => '1234.50', 'idempotency_key' => $key];
        $this->postJson('/panel/islem/kazandir', $body)->assertCreated()->assertJsonPath('data.balance_after', '123.45');
        // Aynı anahtarla ikinci gönderim yeni işlem oluşturmaz
        $this->postJson('/panel/islem/kazandir', $body)->assertOk();
        $this->assertSame(1, WalletTransaction::query()->where('customer_id', $customer->id)->count());

        // Doğrulama hatası JSON ve büyük harfle
        $this->postJson('/panel/islem/kazandir/ozet', ['customer_no' => $customer->customer_no, 'spend_amount' => 'abc'])
            ->assertStatus(422);
    }

    public function test_redeem_request_poll_approve_and_withdraw(): void
    {
        $branch = $this->makeBranch('10.00');
        $manager = $this->makeManager($branch);
        $customer = $this->makeCustomer();
        $this->actingAs($manager);
        $this->postJson('/panel/islem/kazandir', ['customer_no' => $customer->customer_no, 'spend_amount' => '1000', 'idempotency_key' => (string) Str::uuid()])->assertCreated();

        // Bakiyeyi aşan istek reddedilir
        $this->postJson('/panel/islem/kullandir', ['customer_no' => $customer->customer_no, 'amount' => '150', 'idempotency_key' => (string) Str::uuid()])
            ->assertStatus(422);

        $id = $this->postJson('/panel/islem/kullandir', ['customer_no' => $customer->customer_no, 'amount' => '40,00', 'idempotency_key' => (string) Str::uuid()])
            ->assertCreated()->assertJsonPath('data.status', 'pending')->json('data.id');
        $this->getJson("/panel/islem/istek/{$id}")->assertOk()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.balance_after', null);

        // Müşteri uygulamadan onaylar
        app(ApprovalService::class)->approve($customer, $id);
        $this->getJson("/panel/islem/istek/{$id}")->assertOk()
            ->assertJsonPath('data.status', 'approved')->assertJsonPath('data.balance_after', '60.00');

        // İkinci istek → geri çekme
        $id2 = $this->postJson('/panel/islem/kullandir', ['customer_no' => $customer->customer_no, 'amount' => '10', 'idempotency_key' => (string) Str::uuid()])->json('data.id');
        $this->postJson("/panel/islem/istek/{$id2}/geri-cek")->assertOk()->assertJsonPath('data.status', 'withdrawn');
        $this->postJson("/panel/islem/istek/{$id2}/geri-cek")->assertStatus(422);

        // Başka şubenin personeli bu isteği göremez
        $other = $this->makeManager($this->makeBranch());
        $this->actingAs($other)->getJson("/panel/islem/istek/{$id}")->assertNotFound();
    }

    public function test_staff_without_redeem_permission_cannot_redeem(): void
    {
        $branch = $this->makeBranch('10.00');
        $staff = $this->makeStaff($branch, [PanelUser::PERM_LOOKUP, PanelUser::PERM_EARN]);
        $customer = $this->makeCustomer();

        $this->actingAs($staff)
            ->postJson('/panel/islem/kullandir', ['customer_no' => $customer->customer_no, 'amount' => '1', 'idempotency_key' => (string) Str::uuid()])
            ->assertForbidden();
    }

    public function test_suspended_business_blocks_screen_and_json(): void
    {
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        $branch->business->update(['status' => 'suspended']);

        $this->actingAs($manager)->get('/panel/islem')->assertRedirect(route('panel.home'));
        $this->getJson('/panel/islem/musteri/1')->assertStatus(422)->assertJsonPath('code', 'branch_inactive');
    }
}
