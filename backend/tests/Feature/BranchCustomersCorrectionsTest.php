<?php

namespace Tests\Feature;

use App\Domain\Loyalty\ApprovalService;
use App\Models\ApprovalRequest;
use App\Models\PanelUser;
use App\Models\WalletTransaction;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Şube paneli: Müşteriler ve Düzeltmeler. */
class BranchCustomersCorrectionsTest extends TestCase
{
    /** Şube + yönetici + 1000 TL harcamalı (100 TL ödül) müşteri */
    private function setUpBranchWithCustomer(): array
    {
        $branch = $this->makeBranch('10.00');
        $manager = $this->makeManager($branch);
        $staff = $this->makeStaff($branch);
        $customer = $this->makeCustomer(['first_name' => 'Deniz', 'last_name' => 'Arslan']);
        $this->earnAs($staff, $customer, '1000.00');
        $tx = WalletTransaction::query()->where('customer_id', $customer->id)->where('branch_id', $branch->id)->firstOrFail();

        return [$branch, $manager, $staff, $customer, $tx];
    }

    private function correctionForm(WalletTransaction $tx, array $o = []): array
    {
        return array_merge([
            'transaction_id' => $tx->id, 'direction' => 'subtract', 'amount' => '25,00',
            'reason' => 'Hesap tutarı yanlış girildi', 'idempotency_key' => (string) Str::uuid(),
        ], $o);
    }

    public function test_customer_list_and_detail_are_scoped_to_branch(): void
    {
        [$branch, $manager, , $customer, $tx] = $this->setUpBranchWithCustomer();
        // Başka şubenin müşterisi
        $other = $this->makeBranch('10.00');
        $stranger = $this->makeCustomer(['first_name' => 'Yabancı', 'last_name' => 'Müşteri']);
        $this->earnAs($this->makeStaff($other), $stranger, '100.00');

        $this->actingAs($manager, 'web');
        $this->get('/panel/musteriler')->assertOk()
            ->assertSee('Deniz Arslan')->assertSee('100,00 TL', false)->assertDontSee('Yabancı');
        $this->get('/panel/musteriler?q='.$customer->customer_no)->assertOk()->assertSee('Deniz Arslan');
        $this->get('/panel/musteriler?q=deniz')->assertOk()->assertSee('Deniz Arslan');
        $this->get('/panel/musteriler?q=yabanc')->assertOk()->assertDontSee('Yabancı');
        $this->get('/panel/musteriler?siralama=bakiye')->assertOk();

        $this->get('/panel/musteriler/'.$customer->customer_no)->assertOk()
            ->assertSee('İşlem geçmişi')->assertSee('#'.$tx->id)->assertSee('Düzelt')
            ->assertDontSee($customer->email)->assertDontSee($customer->phone);
        $this->get('/panel/musteriler/'.$stranger->customer_no)->assertNotFound();
    }

    public function test_staff_needs_view_customers_permission_and_cannot_correct(): void
    {
        [$branch, , , $customer, $tx] = $this->setUpBranchWithCustomer();

        $noPerm = $this->makeStaff($branch, [PanelUser::PERM_LOOKUP, PanelUser::PERM_EARN]);
        $this->actingAs($noPerm, 'web')->get('/panel/musteriler')->assertForbidden();
        $this->get('/panel/islem')->assertOk()->assertDontSee('Müşteriler');

        $viewer = $this->makeStaff($branch, [PanelUser::PERM_VIEW_CUSTOMERS]);
        $this->actingAs($viewer, 'web')->get('/panel/musteriler')->assertOk()->assertSee('Deniz Arslan');
        $this->get('/panel/musteriler/'.$customer->customer_no)->assertOk()->assertDontSee('>Düzelt</a>', false);
        $this->get('/panel/duzeltmeler')->assertForbidden();
        $this->post('/panel/duzeltmeler', $this->correctionForm($tx))->assertForbidden();
    }

    public function test_correction_request_rules_and_withdraw(): void
    {
        [, $manager, , $customer, $tx] = $this->setUpBranchWithCustomer();
        $this->actingAs($manager, 'web');

        $this->get('/panel/duzeltmeler/yeni?islem='.$tx->id)->assertOk()
            ->assertSee('Düzeltilecek işlem')->assertSee('100,00 TL', false);

        // Doğrulama
        $this->post('/panel/duzeltmeler', $this->correctionForm($tx, ['reason' => '']))->assertSessionHasErrors('reason');
        $this->post('/panel/duzeltmeler', $this->correctionForm($tx, ['amount' => 'abc']))->assertSessionHasErrors('amount');
        $this->post('/panel/duzeltmeler', $this->correctionForm($tx, ['amount' => '0']))->assertSessionHasErrors('amount');

        // Kazanılandan fazlası geri alınamaz
        $this->post('/panel/duzeltmeler', $this->correctionForm($tx, ['amount' => '150']))->assertSessionHas('error');

        $key = (string) Str::uuid();
        $this->post('/panel/duzeltmeler', $this->correctionForm($tx, ['idempotency_key' => $key]))
            ->assertRedirect(route('panel.corrections'))->assertSessionHas('status');
        $req = ApprovalRequest::query()->where('related_transaction_id', $tx->id)->firstOrFail();
        $this->assertSame('-25.00', (string) $req->amount);
        $this->assertSame('pending', $req->status);

        // Aynı anahtarla tekrar gönderim yeni istek açmaz; farklı anahtarla ikinci bekleyen istek engellenir
        $this->post('/panel/duzeltmeler', $this->correctionForm($tx, ['idempotency_key' => $key]));
        $this->post('/panel/duzeltmeler', $this->correctionForm($tx))->assertSessionHas('error');
        $this->assertSame(1, ApprovalRequest::query()->where('related_transaction_id', $tx->id)->count());

        $this->get('/panel/duzeltmeler')->assertOk()->assertSee('Deniz Arslan')->assertSee('-25,00 TL', false);
        $this->get('/panel/musteriler/'.$customer->customer_no)->assertSee('Düzeltme bekliyor');

        $this->post("/panel/duzeltmeler/{$req->id}/geri-cek")->assertSessionHas('status');
        $this->assertSame('withdrawn', $req->refresh()->status);
        $this->post("/panel/duzeltmeler/{$req->id}/geri-cek")->assertSessionHas('error');
    }

    public function test_customer_approval_applies_correction_and_rejection_does_not(): void
    {
        [, $manager, , $customer, $tx] = $this->setUpBranchWithCustomer();
        $this->actingAs($manager, 'web');

        $this->post('/panel/duzeltmeler', $this->correctionForm($tx, ['amount' => '30']));
        $req = ApprovalRequest::query()->where('related_transaction_id', $tx->id)->firstOrFail();

        app(ApprovalService::class)->reject($customer, $req->id);
        $this->assertSame('100.00', $this->walletBalance($tx->branch, $customer));
        $this->get('/panel/duzeltmeler?durum=rejected')->assertOk()->assertSee('Reddedildi');

        // Ekleme yönünde yeni düzeltme → müşteri onaylar → bakiye artar
        $this->post('/panel/duzeltmeler', $this->correctionForm($tx, ['direction' => 'add', 'amount' => '12,50']));
        $req2 = ApprovalRequest::query()->where('related_transaction_id', $tx->id)->where('status', 'pending')->firstOrFail();
        $this->assertSame('12.50', (string) $req2->amount);
        app(ApprovalService::class)->approve($customer, $req2->id);
        $this->assertSame('112.50', $this->walletBalance($tx->branch, $customer));

        $this->get('/panel/duzeltmeler?durum=approved')->assertOk()->assertSee('Yeni bakiye 112,50 TL', false);
        $this->get('/panel/musteriler/'.$customer->customer_no)->assertOk()->assertSee('Düzeltme');
    }

    public function test_other_branch_transaction_cannot_be_corrected(): void
    {
        [, , , , $tx] = $this->setUpBranchWithCustomer();
        $otherManager = $this->makeManager($this->makeBranch());

        $this->actingAs($otherManager, 'web')->get('/panel/duzeltmeler/yeni?islem='.$tx->id)->assertNotFound();
        $this->post('/panel/duzeltmeler', $this->correctionForm($tx))->assertSessionHas('error');
        $this->assertSame(0, ApprovalRequest::query()->where('related_transaction_id', $tx->id)->count());
    }
}
