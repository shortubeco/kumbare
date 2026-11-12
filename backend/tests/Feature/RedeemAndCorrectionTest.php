<?php

namespace Tests\Feature;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RedeemAndCorrectionTest extends TestCase
{
    public function test_redeem_changes_balance_only_after_customer_approval(): void
    {
        $branch = $this->makeBranch('10.00');
        $staff = $this->makeStaff($branch);
        $customer = $this->makeCustomer();
        $this->earnAs($staff, $customer, '4500')->assertCreated(); // 450 TL

        $requestId = $this->redeemAs($staff, $customer, '300')->assertCreated()
            ->assertJsonPath('data.status', 'pending')->json('data.id');

        $this->assertSame('450.00', $this->walletBalance($branch, $customer));

        // Müşteri Bildirimler ekranında isteği görür.
        Sanctum::actingAs($customer);
        $this->getJson('/api/v1/approvals')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.amount', '300.00')
            ->assertJsonPath('data.0.balance_after', '150.00');

        $this->postJson("/api/v1/approvals/{$requestId}/approve")->assertOk()->assertJsonPath('approved', true);
        $this->assertSame('150.00', $this->walletBalance($branch, $customer));

        // Personel onayı görür.
        Sanctum::actingAs($staff);
        $this->getJson("/api/panel/v1/approval-requests/{$requestId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.balance_after', '150.00');

        // İkinci onay denemesi.
        Sanctum::actingAs($customer);
        $this->postJson("/api/v1/approvals/{$requestId}/approve")->assertStatus(409);
    }

    public function test_insufficient_balance_at_approval_rejects_request(): void
    {
        $branch = $this->makeBranch('10.00');
        $staff = $this->makeStaff($branch);
        $customer = $this->makeCustomer();
        $this->earnAs($staff, $customer, '1000')->assertCreated(); // 100 TL

        $first = $this->redeemAs($staff, $customer, '80')->assertCreated()->json('data.id');
        $second = $this->redeemAs($staff, $customer, '80')->assertCreated()->json('data.id');

        Sanctum::actingAs($customer);
        $this->postJson("/api/v1/approvals/{$first}/approve")->assertOk();
        $this->postJson("/api/v1/approvals/{$second}/approve")
            ->assertStatus(422)
            ->assertJsonPath('approved', false)
            ->assertJsonPath('data.status', 'rejected');

        $this->assertSame('20.00', $this->walletBalance($branch, $customer));
    }

    public function test_customer_cannot_approve_someone_elses_request(): void
    {
        $branch = $this->makeBranch('10.00');
        $staff = $this->makeStaff($branch);
        $owner = $this->makeCustomer();
        $this->earnAs($staff, $owner, '1000')->assertCreated();
        $requestId = $this->redeemAs($staff, $owner, '50')->json('data.id');

        Sanctum::actingAs($this->makeCustomer());
        $this->postJson("/api/v1/approvals/{$requestId}/approve")->assertStatus(404);
        $this->assertSame('100.00', $this->walletBalance($branch, $owner));
    }

    public function test_staff_can_withdraw_pending_request(): void
    {
        $branch = $this->makeBranch('10.00');
        $staff = $this->makeStaff($branch);
        $customer = $this->makeCustomer();
        $this->earnAs($staff, $customer, '1000')->assertCreated();
        $requestId = $this->redeemAs($staff, $customer, '50')->json('data.id');

        Sanctum::actingAs($staff);
        $this->postJson("/api/panel/v1/approval-requests/{$requestId}/withdraw")
            ->assertOk()->assertJsonPath('data.status', 'withdrawn');

        Sanctum::actingAs($customer);
        $this->getJson('/api/v1/approvals')->assertJsonCount(0, 'data');
        $this->postJson("/api/v1/approvals/{$requestId}/approve")->assertStatus(409);
    }

    public function test_correction_requires_manager_and_customer_approval(): void
    {
        $branch = $this->makeBranch('10.00');
        $manager = $this->makeManager($branch);
        $staff = $this->makeStaff($branch);
        $customer = $this->makeCustomer();

        // Personel yanlışlıkla 10.000 TL girdi → 1.000 TL ödül.
        $txId = $this->earnAs($staff, $customer, '10000')->assertCreated()->json('data.id');

        $payload = ['transaction_id' => $txId, 'amount' => '-900', 'reason' => 'Tutar hatalı girildi', 'idempotency_key' => (string) Str::uuid()];

        Sanctum::actingAs($staff);
        $this->postJson('/api/panel/v1/corrections', $payload)->assertStatus(403);

        Sanctum::actingAs($manager);
        $requestId = $this->postJson('/api/panel/v1/corrections', $payload)->assertCreated()->json('data.id');
        $this->assertSame('1000.00', $this->walletBalance($branch, $customer));

        Sanctum::actingAs($customer);
        $this->getJson('/api/v1/approvals')
            ->assertJsonPath('data.0.type', 'correction')
            ->assertJsonPath('data.0.reason', 'Tutar hatalı girildi')
            ->assertJsonPath('data.0.related_transaction.amount', '1000.00');
        $this->postJson("/api/v1/approvals/{$requestId}/approve")->assertOk();

        $wallet = Wallet::query()->where('branch_id', $branch->id)->where('customer_id', $customer->id)->first();
        $this->assertSame('100.00', (string) $wallet->balance);
        $this->assertSame('100.00', (string) $wallet->total_earned);

        // Orijinal işlem silinmedi; düzeltme ayrı kayıt.
        $this->assertSame(2, WalletTransaction::query()->where('wallet_id', $wallet->id)->count());
    }

    public function test_rejected_correction_leaves_balance_unchanged(): void
    {
        $branch = $this->makeBranch('10.00');
        $manager = $this->makeManager($branch);
        $customer = $this->makeCustomer();
        $txId = $this->earnAs($manager, $customer, '1000')->assertCreated()->json('data.id');

        Sanctum::actingAs($manager);
        $requestId = $this->postJson('/api/panel/v1/corrections', [
            'transaction_id' => $txId, 'amount' => '-50', 'reason' => 'Hata', 'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($customer);
        $this->postJson("/api/v1/approvals/{$requestId}/reject")->assertOk();

        $this->assertSame('100.00', $this->walletBalance($branch, $customer));
    }

    public function test_correction_cannot_exceed_original_earn(): void
    {
        $branch = $this->makeBranch('10.00');
        $manager = $this->makeManager($branch);
        $customer = $this->makeCustomer();
        $txId = $this->earnAs($manager, $customer, '1000')->json('data.id');

        Sanctum::actingAs($manager);
        $this->postJson('/api/panel/v1/corrections', [
            'transaction_id' => $txId, 'amount' => '-150', 'reason' => 'Hata', 'idempotency_key' => (string) Str::uuid(),
        ])->assertStatus(422);
    }

    public function test_customer_wallet_endpoints(): void
    {
        $branch = $this->makeBranch('10.00');
        $staff = $this->makeStaff($branch);
        $customer = $this->makeCustomer();
        $this->earnAs($staff, $customer, '1000')->assertCreated();

        Sanctum::actingAs($customer);
        $walletId = $this->getJson('/api/v1/wallets')->assertOk()
            ->assertJsonPath('data.0.balance', '100.00')->json('data.0.id');

        $this->getJson("/api/v1/wallets/{$walletId}/transactions")
            ->assertOk()
            ->assertJsonPath('data.0.label', 'Kazanıldı')
            ->assertJsonPath('data.0.spend_amount', '1000.00');

        Sanctum::actingAs($this->makeCustomer());
        $this->getJson("/api/v1/wallets/{$walletId}")->assertStatus(404);
    }
}
