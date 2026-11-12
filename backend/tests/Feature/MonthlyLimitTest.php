<?php

namespace Tests\Feature;

use App\Models\MonthlyBranchCounter;
use App\Models\PanelNotification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MonthlyLimitTest extends TestCase
{
    public function test_new_customer_is_rejected_when_limit_is_reached(): void
    {
        $this->setLimit(2);
        $branch = $this->makeBranch();
        $staff = $this->makeStaff($branch);
        [$c1, $c2, $c3] = [$this->makeCustomer(), $this->makeCustomer(), $this->makeCustomer()];

        $this->earnAs($staff, $c1, '100')->assertCreated();
        $this->earnAs($staff, $c2, '100')->assertCreated();

        $this->earnAs($staff, $c3, '100')
            ->assertStatus(422)
            ->assertJsonPath('code', 'monthly_limit_reached');

        // Bu ay zaten sayılmış müşteriye ödül kazandırmak serbest.
        $this->earnAs($staff, $c1, '100')->assertCreated();
    }

    public function test_redeem_is_allowed_when_limit_is_reached(): void
    {
        $this->setLimit(1);
        $branch = $this->makeBranch();
        $staff = $this->makeStaff($branch);
        $c1 = $this->makeCustomer();

        $this->earnAs($staff, $c1, '1000')->assertCreated();
        $this->earnAs($staff, $this->makeCustomer(), '100')->assertStatus(422);

        $this->redeemAs($staff, $c1, '50')->assertCreated();
    }

    public function test_pro_branch_has_no_limit(): void
    {
        $this->setLimit(1);
        $branch = $this->makeBranch();
        $this->makePro($branch);
        $staff = $this->makeStaff($branch);

        $this->earnAs($staff, $this->makeCustomer(), '100')->assertCreated();
        $this->earnAs($staff, $this->makeCustomer(), '100')->assertCreated();
        $this->earnAs($staff, $this->makeCustomer(), '100')->assertCreated();
    }

    public function test_counter_starts_from_zero_in_new_month(): void
    {
        $this->setLimit(1);
        $branch = $this->makeBranch();
        $staff = $this->makeStaff($branch);
        $customer = $this->makeCustomer();

        $this->earnAs($staff, $customer, '100')->assertCreated();
        $this->earnAs($staff, $this->makeCustomer(), '100')->assertStatus(422);

        $this->travelTo(now()->addMonthNoOverflow()->startOfMonth()->addHour());

        $this->earnAs($staff, $this->makeCustomer(), '100')->assertCreated();
        $this->assertSame(2, MonthlyBranchCounter::query()->where('branch_id', $branch->id)->count());
    }

    public function test_each_branch_has_its_own_counter(): void
    {
        $this->setLimit(1);
        $branchA = $this->makeBranch();
        $branchB = $this->makeBranch('10.00', [], $branchA->business);
        $customer = $this->makeCustomer();

        $this->earnAs($this->makeStaff($branchA), $customer, '100')->assertCreated();
        $this->earnAs($this->makeStaff($branchB), $customer, '100')->assertCreated();
        $this->earnAs($this->makeStaff($branchA), $this->makeCustomer(), '100')->assertStatus(422);
    }

    public function test_threshold_notifications_are_sent_once(): void
    {
        $this->setLimit(10);
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        $staff = $this->makeStaff($branch);

        for ($i = 0; $i < 10; $i++) {
            $this->earnAs($staff, $this->makeCustomer(), '100')->assertCreated();
        }

        $types = PanelNotification::query()->where('panel_user_id', $manager->id)->orderBy('id')->pluck('type')->all();
        $this->assertSame(['threshold_1', 'threshold_2', 'limit_reached'], $types);
    }

    public function test_panel_me_shows_counter_status(): void
    {
        $this->setLimit(5);
        $branch = $this->makeBranch();
        $staff = $this->makeStaff($branch);
        $this->earnAs($staff, $this->makeCustomer(), '100')->assertCreated();

        Sanctum::actingAs($staff);
        $this->getJson('/api/panel/v1/me')
            ->assertOk()
            ->assertJsonPath('data.monthly_counter.unique_customers', 1)
            ->assertJsonPath('data.monthly_counter.limit', 5)
            ->assertJsonPath('data.monthly_counter.plan', 'free');
    }
}
