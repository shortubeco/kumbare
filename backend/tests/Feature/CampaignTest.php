<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CampaignTest extends TestCase
{
    public function test_selected_campaign_rate_is_applied(): void
    {
        $branch = $this->makeBranch('10.00');
        $staff = $this->makeStaff($branch);
        $campaign = $this->makeRateCampaign($branch, '15.00');
        $customer = $this->makeCustomer();

        $this->earnAs($staff, $customer, '1000', $campaign->id)
            ->assertCreated()
            ->assertJsonPath('data.amount', '150.00')
            ->assertJsonPath('data.campaign.id', $campaign->id);

        // Kampanya seçilmezse şube oranı.
        $this->earnAs($staff, $customer, '1000')->assertJsonPath('data.amount', '100.00');
    }

    public function test_birthday_campaign_is_listed_only_on_birthday(): void
    {
        $this->travelTo(Carbon::create(2026, 6, 10, 12));
        $branch = $this->makeBranch('10.00');
        $staff = $this->makeStaff($branch);
        $birthday = $this->makeRateCampaign($branch, '25.00', ['birthday_only' => true]);

        $todayCustomer = $this->makeCustomer(['birth_date' => '1995-06-10']);
        $otherCustomer = $this->makeCustomer(['birth_date' => '1995-06-11']);

        Sanctum::actingAs($staff);
        $this->getJson("/api/panel/v1/customers/{$todayCustomer->customer_no}/campaigns")
            ->assertOk()->assertJsonPath('data.0.id', $birthday->id);
        $this->getJson("/api/panel/v1/customers/{$otherCustomer->customer_no}/campaigns")
            ->assertOk()->assertJsonCount(0, 'data');

        $this->earnAs($staff, $otherCustomer, '100', $birthday->id)
            ->assertStatus(422)->assertJsonPath('code', 'campaign_not_eligible');
        $this->earnAs($staff, $todayCustomer, '100', $birthday->id)->assertCreated();
    }

    public function test_feb_29_birthday_is_celebrated_on_feb_28_in_non_leap_years(): void
    {
        $customer = $this->makeCustomer(['birth_date' => '2000-02-29']);

        $this->assertTrue($customer->isBirthday(Carbon::create(2027, 2, 28)));
        $this->assertFalse($customer->isBirthday(Carbon::create(2027, 3, 1)));
        $this->assertTrue($customer->isBirthday(Carbon::create(2028, 2, 29)));
        $this->assertFalse($customer->isBirthday(Carbon::create(2028, 2, 28)));
    }

    public function test_new_customer_campaign_only_for_first_earn(): void
    {
        $branch = $this->makeBranch('10.00');
        $staff = $this->makeStaff($branch);
        $welcome = $this->makeRateCampaign($branch, '20.00', ['new_customers_only' => true]);
        $customer = $this->makeCustomer();

        $this->earnAs($staff, $customer, '100', $welcome->id)->assertCreated();
        $this->earnAs($staff, $customer, '100', $welcome->id)->assertStatus(422);
    }

    public function test_weekday_campaign(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 14, 12)); // Cumartesi
        $branch = $this->makeBranch('10.00');
        $staff = $this->makeStaff($branch);
        $weekday = $this->makeRateCampaign($branch, '15.00', ['weekdays' => '{1,2,3,4,5}']);
        $customer = $this->makeCustomer();

        $this->earnAs($staff, $customer, '100', $weekday->id)->assertStatus(422);

        $this->travelTo(Carbon::create(2026, 11, 16, 12)); // Pazartesi
        $this->earnAs($staff, $customer, '100', $weekday->id)->assertCreated();
    }

    public function test_campaign_lets_zero_rate_branch_reward(): void
    {
        $branch = $this->makeBranch('0.00');
        $staff = $this->makeStaff($branch);
        $campaign = $this->makeRateCampaign($branch, '5.00');

        $this->earnAs($staff, $this->makeCustomer(), '200', $campaign->id)
            ->assertCreated()->assertJsonPath('data.amount', '10.00');
    }

    public function test_customer_sees_campaigns_in_registered_district(): void
    {
        $branch = $this->makeBranch('10.00');
        $this->makeRateCampaign($branch, '15.00', ['title' => 'Kadıköy kampanyası']);
        $customer = $this->makeCustomer();

        Sanctum::actingAs($customer);
        $this->getJson('/api/v1/campaigns')
            ->assertOk()
            ->assertJsonPath('district.name', 'Kadıköy')
            ->assertJsonFragment(['title' => 'Kadıköy kampanyası']);
    }
}
