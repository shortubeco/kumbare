<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerAccountTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        $n = $this->next();
        $district = $this->kadikoy();

        return array_merge([
            'first_name' => 'Ayşe',
            'last_name' => 'Demir',
            'phone' => '0532'.str_pad((string) ($n % 10000000), 7, '0', STR_PAD_LEFT),
            'city_id' => 34,
            'district_id' => $district->id,
            'birth_date' => '1992-03-10',
            'email' => "ayse{$n}@test.local",
            'password' => 'guclu-sifre-1',
            'password_confirmation' => 'guclu-sifre-1',
            'kvkk_consent' => true,
        ], $overrides);
    }

    public function test_registration_assigns_sequential_customer_numbers(): void
    {
        $first = $this->postJson('/api/v1/auth/register', $this->payload())->assertCreated();
        $second = $this->postJson('/api/v1/auth/register', $this->payload())->assertCreated();

        $no1 = $first->json('customer.customer_no');
        $no2 = $second->json('customer.customer_no');

        $this->assertSame($no1 + 1, $no2);
        $this->assertSame($no2, (int) DB::table('customer_no_counter')->value('last_value'));
        $this->assertNotEmpty($first->json('token'));
    }

    public function test_registration_validates_district_belongs_to_city(): void
    {
        $ankaraDistrict = DB::table('districts')->where('city_id', 6)->value('id');

        $this->postJson('/api/v1/auth/register', $this->payload(['district_id' => $ankaraDistrict]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('district_id');
    }

    public function test_duplicate_email_is_rejected_case_insensitively(): void
    {
        $payload = $this->payload();
        $this->postJson('/api/v1/auth/register', $payload)->assertCreated();

        $this->postJson('/api/v1/auth/register', $this->payload(['email' => strtoupper($payload['email'])]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_login_and_profile(): void
    {
        $payload = $this->payload();
        $this->postJson('/api/v1/auth/register', $payload)->assertCreated();

        $login = $this->postJson('/api/v1/auth/login', ['email' => $payload['email'], 'password' => $payload['password']])
            ->assertOk();

        $this->withToken($login->json('token'))
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', $payload['email'])
            ->assertJsonPath('data.public_name', 'Ayşe D.');

        $this->postJson('/api/v1/auth/login', ['email' => $payload['email'], 'password' => 'yanlis'])
            ->assertStatus(422);
    }

    public function test_birth_date_and_customer_number_cannot_be_changed_by_customer(): void
    {
        $customer = $this->makeCustomer();
        $originalNo = $customer->customer_no;
        Sanctum::actingAs($customer);

        $this->putJson('/api/v1/me', [
            'first_name' => 'Mehmet',
            'birth_date' => '2000-01-01',
            'customer_no' => 1,
        ])->assertOk()->assertJsonPath('data.first_name', 'Mehmet');

        $fresh = Customer::query()->find($customer->id);
        $this->assertSame('1990-05-15', $fresh->birth_date->toDateString());
        $this->assertSame($originalNo, $fresh->customer_no);
    }

    public function test_deleted_account_is_anonymized_and_number_is_kept(): void
    {
        $customer = $this->makeCustomer();
        Sanctum::actingAs($customer);

        $this->deleteJson('/api/v1/me', ['password' => 'secret-password'])->assertOk();

        $row = DB::table('customers')->where('id', $customer->id)->first();
        $this->assertNotNull($row->deleted_at);
        $this->assertSame('Silinmiş', $row->first_name);
        $this->assertSame($customer->customer_no, (int) $row->customer_no);
    }

    public function test_protected_endpoints_require_customer_token(): void
    {
        $this->getJson('/api/v1/wallets')->assertStatus(401)->assertJsonPath('code', 'unauthenticated');

        $branch = $this->makeBranch();
        Sanctum::actingAs($this->makeStaff($branch));
        $this->getJson('/api/v1/wallets')->assertStatus(403);
    }
}
