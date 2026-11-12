<?php

namespace Tests\Feature;

use App\Models\MonthlyBranchCounter;
use App\Models\PanelUser;
use App\Models\WalletTransaction;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EarnTest extends TestCase
{
    public function test_reward_is_calculated_with_kurus_rounding(): void
    {
        $branch = $this->makeBranch('7.00');
        $staff = $this->makeStaff($branch);
        $customer = $this->makeCustomer();

        // 1.234,56 TL × %7 = 86,4192 → 86,42 TL
        $this->earnAs($staff, $customer, '1234.56')
            ->assertCreated()
            ->assertJsonPath('data.amount', '86.42')
            ->assertJsonPath('data.reward_rate', '7.00')
            ->assertJsonPath('data.balance_after', '86.42');

        $this->assertSame('86.42', $this->walletBalance($branch, $customer));
    }

    public function test_rate_change_does_not_affect_past_transactions(): void
    {
        $branch = $this->makeBranch('10.00');
        $staff = $this->makeStaff($branch);
        $customer = $this->makeCustomer();

        $this->earnAs($staff, $customer, '1000')->assertCreated();
        $branch->update(['reward_rate' => '5.00']);
        $staff->refresh();
        $this->earnAs($staff, $customer, '1000')->assertCreated()->assertJsonPath('data.amount', '50.00');

        $rates = WalletTransaction::query()->where('customer_id', $customer->id)->orderBy('id')->pluck('reward_rate')->map(fn ($r) => (string) $r)->all();
        $this->assertSame(['10.00', '5.00'], $rates);
        $this->assertSame('150.00', $this->walletBalance($branch, $customer));
    }

    public function test_wallets_are_isolated_per_branch(): void
    {
        $branchA = $this->makeBranch('10.00');
        $branchB = $this->makeBranch('10.00', [], $branchA->business); // aynı markanın başka şubesi
        $customer = $this->makeCustomer();

        $this->earnAs($this->makeStaff($branchA), $customer, '1000')->assertCreated();

        $this->assertSame('100.00', $this->walletBalance($branchA, $customer));
        $this->assertNull($this->walletBalance($branchB, $customer));

        // B şubesinde A'nın bakiyesi kullanılamaz.
        $this->redeemAs($this->makeStaff($branchB), $customer, '50')
            ->assertStatus(422)
            ->assertJsonPath('code', 'insufficient_balance');
    }

    public function test_zero_rate_branch_cannot_earn(): void
    {
        $branch = $this->makeBranch('0.00');
        $this->earnAs($this->makeStaff($branch), $this->makeCustomer(), '500')
            ->assertStatus(422)
            ->assertJsonPath('code', 'reward_disabled');
    }

    public function test_staff_without_earn_permission_is_forbidden(): void
    {
        $branch = $this->makeBranch();
        $staff = $this->makeStaff($branch, [PanelUser::PERM_LOOKUP]);

        $this->earnAs($staff, $this->makeCustomer(), '100')->assertStatus(403);
    }

    public function test_same_idempotency_key_does_not_create_duplicate(): void
    {
        $branch = $this->makeBranch();
        $staff = $this->makeStaff($branch);
        $customer = $this->makeCustomer();
        Sanctum::actingAs($staff);

        $payload = ['customer_no' => $customer->customer_no, 'spend_amount' => '200', 'idempotency_key' => (string) Str::uuid()];
        $this->postJson('/api/panel/v1/earn', $payload)->assertCreated();
        $this->postJson('/api/panel/v1/earn', $payload)->assertOk();

        $this->assertSame(1, WalletTransaction::query()->where('customer_id', $customer->id)->count());
        $this->assertSame('20.00', $this->walletBalance($branch, $customer));
    }

    public function test_counter_counts_customer_once_per_month(): void
    {
        $branch = $this->makeBranch();
        $staff = $this->makeStaff($branch);
        $customer = $this->makeCustomer();

        for ($i = 0; $i < 3; $i++) {
            $this->earnAs($staff, $customer, '100')->assertCreated();
        }
        $this->earnAs($staff, $this->makeCustomer(), '100')->assertCreated();

        $counter = MonthlyBranchCounter::query()->where('branch_id', $branch->id)->first();
        $this->assertSame(2, $counter->unique_customers);
    }

    public function test_lookup_shows_name_and_birthday_flag_but_not_birth_date(): void
    {
        $branch = $this->makeBranch();
        $staff = $this->makeStaff($branch);
        $customer = $this->makeCustomer(['birth_date' => now()->subYears(30)->toDateString()]);
        Sanctum::actingAs($staff);

        $response = $this->getJson("/api/panel/v1/customers/{$customer->customer_no}")
            ->assertOk()
            ->assertJsonPath('data.customer.full_name', 'Ahmet Yılmaz')
            ->assertJsonPath('data.customer.is_birthday_today', true)
            ->assertJsonPath('data.wallet.balance', '0.00');

        $this->assertStringNotContainsString('birth_date', $response->getContent());
        $this->getJson('/api/panel/v1/customers/999999999')->assertStatus(404);
    }

    public function test_preview_matches_earn_without_writing(): void
    {
        $branch = $this->makeBranch('12.50');
        $staff = $this->makeStaff($branch);
        $customer = $this->makeCustomer();
        Sanctum::actingAs($staff);

        $this->postJson('/api/panel/v1/earn/preview', ['customer_no' => $customer->customer_no, 'spend_amount' => '80,40'])
            ->assertOk()
            ->assertJsonPath('data.reward_amount', '10.05')
            ->assertJsonPath('data.can_earn', true);

        $this->assertNull($this->walletBalance($branch, $customer));
    }
}
