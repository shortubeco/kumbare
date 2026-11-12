<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Support\TurkishText;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Canlı QA testinde bulunan hataların tekrar etmemesi için. */
class QaRegressionTest extends TestCase
{
    public function test_changing_city_requires_district(): void
    {
        $customer = $this->makeCustomer();
        Sanctum::actingAs($customer);

        $this->putJson('/api/v1/me', ['city_id' => 6])
            ->assertStatus(422)
            ->assertJsonValidationErrors('district_id');

        $this->assertSame(34, Customer::query()->find($customer->id)->city_id);

        $ankaraDistrict = DB::table('districts')->where('city_id', 6)->value('id');
        $this->putJson('/api/v1/me', ['city_id' => 6, 'district_id' => $ankaraDistrict])
            ->assertOk()
            ->assertJsonPath('data.city.id', 6);
    }

    public function test_district_must_belong_to_current_city(): void
    {
        Sanctum::actingAs($this->makeCustomer()); // İstanbul
        $ankaraDistrict = DB::table('districts')->where('city_id', 6)->value('id');

        $this->putJson('/api/v1/me', ['district_id' => $ankaraDistrict])->assertStatus(422);
    }

    public function test_non_numeric_city_returns_404_not_500(): void
    {
        $this->getJson('/api/v1/cities/abc/districts')
            ->assertStatus(404)
            ->assertJsonPath('code', 'not_found');
    }

    public function test_method_not_allowed_is_turkish_json(): void
    {
        $this->deleteJson('/api/v1/cities')
            ->assertStatus(405)
            ->assertJsonPath('code', 'method_not_allowed')
            ->assertJsonPath('message', 'Bu adres bu istek türünü (DELETE) desteklemiyor.');
    }

    public function test_root_does_not_show_framework_welcome_page(): void
    {
        // WEB_DOMAIN tanımlı değilken ana adres web sitesinin ana sayfasıdır.
        $this->get('/')
            ->assertOk()
            ->assertSee('Hesabı öde, ödülün kumbarana düşsün.')
            ->assertDontSee('Laravel');
    }

    public function test_security_headers(): void
    {
        $response = $this->getJson('/api/v1/categories');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeaderMissing('X-Powered-By');

        $this->get('https://localhost/api/v1/categories')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_validation_messages_start_with_capital_letter_and_have_no_english_summary(): void
    {
        $response = $this->getJson('/api/v1/home?lat=41.01')->assertStatus(422);
        $this->assertSame('Enlem girildiğinde boylam alanı zorunludur.', $response->json('message'));

        $response = $this->postJson('/api/v1/auth/register', [])->assertStatus(422);
        $this->assertStringNotContainsString('more error', $response->json('message'));
        $this->assertSame('İlçe alanı zorunludur.', $response->json('errors.district_id.0'));
        $this->assertSame('Ad alanı zorunludur.', $response->json('errors.first_name.0'));
    }

    public function test_turkish_ucfirst(): void
    {
        $this->assertSame('İlçe', TurkishText::ucfirst('ilçe'));
        $this->assertSame('Isparta', TurkishText::ucfirst('ısparta'));
        $this->assertSame('Şifre', TurkishText::ucfirst('şifre'));
        $this->assertSame('', TurkishText::ucfirst(''));
    }
}
