<?php

namespace Tests;

use App\Domain\Customers\CustomerRegistrar;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\District;
use App\Models\PanelUser;
use App\Models\Setting;
use App\Models\StaffPermission;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * Testler kumbare_test veritabanında çalışır (şema database/*.sql ile yüklenir).
 * Her test bir transaction içinde çalışır ve sonunda geri alınır.
 */
abstract class TestCase extends BaseTestCase
{
    use DatabaseTransactions;

    private static int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        if (! str_ends_with((string) DB::connection()->getDatabaseName(), '_test')) {
            $this->fail('Testler yalnızca *_test veritabanında çalıştırılabilir.');
        }

        Setting::flushCache();
    }

    protected function next(): int
    {
        return ++self::$seq + random_int(1000, 9999) * 1000;
    }

    protected function kadikoy(): District
    {
        return District::query()->where('city_id', 34)->where('slug', 'kadikoy')->firstOrFail();
    }

    protected function makeBranch(string $rate = '10.00', array $attrs = [], ?Business $business = null): Branch
    {
        $n = $this->next();
        $business ??= Business::query()->create([
            'name' => "Test Restoran {$n}",
            'slug' => "test-restoran-{$n}",
            'status' => 'approved',
        ]);
        $district = $this->kadikoy();

        return Branch::query()->create(array_merge([
            'business_id' => $business->id,
            'name' => "Şube {$n}",
            'slug' => "sube-{$n}",
            'city_id' => $district->city_id,
            'district_id' => $district->id,
            'address' => 'Test adres',
            'latitude' => '40.9900000',
            'longitude' => '29.0300000',
            'reward_rate' => $rate,
            'status' => 'active',
        ], $attrs))->load('business');
    }

    protected function makeManager(Branch $branch): PanelUser
    {
        $n = $this->next();

        return PanelUser::query()->create([
            'role' => PanelUser::ROLE_BRANCH_MANAGER,
            'business_id' => $branch->business_id,
            'branch_id' => $branch->id,
            'first_name' => 'Yönetici',
            'last_name' => "Test{$n}",
            'email' => "manager{$n}@test.local",
            'password' => 'secret-password',
            'status' => 'active',
        ]);
    }

    protected function makeStaff(Branch $branch, array $permissions = [PanelUser::PERM_LOOKUP, PanelUser::PERM_EARN, PanelUser::PERM_REDEEM]): PanelUser
    {
        $n = $this->next();
        $staff = PanelUser::query()->create([
            'role' => PanelUser::ROLE_STAFF,
            'business_id' => $branch->business_id,
            'branch_id' => $branch->id,
            'first_name' => 'Personel',
            'last_name' => "Test{$n}",
            'email' => "staff{$n}@test.local",
            'password' => 'secret-password',
            'status' => 'active',
        ]);

        foreach ($permissions as $perm) {
            StaffPermission::query()->create(['panel_user_id' => $staff->id, 'permission' => $perm]);
        }

        return $staff->load('permissions');
    }

    protected function makeCustomer(array $overrides = []): Customer
    {
        $n = $this->next();
        $district = $this->kadikoy();

        return app(CustomerRegistrar::class)->register(array_merge([
            'first_name' => 'Ahmet',
            'last_name' => 'Yılmaz',
            'phone' => '0555'.str_pad((string) ($n % 10000000), 7, '0', STR_PAD_LEFT),
            'email' => "musteri{$n}@test.local",
            'password' => 'secret-password',
            'city_id' => $district->city_id,
            'district_id' => $district->id,
            'birth_date' => '1990-05-15',
        ], $overrides));
    }

    protected function makeRateCampaign(Branch $branch, string $rate, array $attrs = []): Campaign
    {
        $creator = PanelUser::query()->where('branch_id', $branch->id)->first() ?? $this->makeManager($branch);

        $campaign = new Campaign(array_merge([
            'branch_id' => $branch->id,
            'title' => "%{$rate} kampanya",
            'type' => Campaign::TYPE_RATE_OVERRIDE,
            'reward_rate' => $rate,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'status' => 'active',
            'created_by' => $creator->id,
        ], $attrs));
        $campaign->save();

        return $campaign;
    }

    protected function makePro(Branch $branch): Subscription
    {
        $admin = PanelUser::query()->create([
            'role' => PanelUser::ROLE_PLATFORM_ADMIN,
            'first_name' => 'Admin',
            'last_name' => 'Test',
            'email' => 'admin'.$this->next().'@test.local',
            'password' => 'secret-password',
            'status' => 'active',
        ]);

        return Subscription::query()->create([
            'branch_id' => $branch->id,
            'billing_cycle' => 'monthly',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonths(2),
            'price' => '500.00',
            'status' => 'active',
            'activated_by' => $admin->id,
        ]);
    }

    /** Personel olarak ödül kazandırır (API üzerinden). */
    protected function earnAs(PanelUser $staff, Customer $customer, string $spend, ?int $campaignId = null)
    {
        Sanctum::actingAs($staff);

        return $this->postJson('/api/panel/v1/earn', array_filter([
            'customer_no' => $customer->customer_no,
            'spend_amount' => $spend,
            'campaign_id' => $campaignId,
            'idempotency_key' => (string) Str::uuid(),
        ], fn ($v) => $v !== null));
    }

    protected function redeemAs(PanelUser $staff, Customer $customer, string $amount)
    {
        Sanctum::actingAs($staff);

        return $this->postJson('/api/panel/v1/redeem', [
            'customer_no' => $customer->customer_no,
            'amount' => $amount,
            'idempotency_key' => (string) Str::uuid(),
        ]);
    }

    protected function setLimit(int $limit): void
    {
        Setting::put('free_monthly_customer_limit', (string) $limit);
    }

    protected function walletBalance(Branch $branch, Customer $customer): ?string
    {
        return DB::table('wallets')->where('branch_id', $branch->id)->where('customer_id', $customer->id)->value('balance');
    }
}
