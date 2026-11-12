<?php

namespace Tests\Feature;

use App\Models\Branch;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReviewAndCatalogTest extends TestCase
{
    public function test_customer_can_review_only_after_earning(): void
    {
        $branch = $this->makeBranch('10.00');
        $staff = $this->makeStaff($branch);
        $customer = $this->makeCustomer();

        Sanctum::actingAs($customer);
        $this->putJson("/api/v1/branches/{$branch->id}/review", ['score' => 9, 'comment' => 'Harika'])
            ->assertStatus(403)->assertJsonPath('code', 'review_not_allowed');

        $this->earnAs($staff, $customer, '100')->assertCreated();

        Sanctum::actingAs($customer);
        $this->putJson("/api/v1/branches/{$branch->id}/review", ['score' => 9, 'comment' => 'Harika'])->assertOk();
        // Aynı müşteri ikinci kez gönderirse mevcut değerlendirme güncellenir.
        $this->putJson("/api/v1/branches/{$branch->id}/review", ['score' => 7, 'comment' => 'İyi'])->assertOk();

        $branch->refresh();
        $this->assertSame(1, $branch->rating_count);
        $this->assertSame('7.0', (string) $branch->rating_avg);

        $this->putJson("/api/v1/branches/{$branch->id}/review", ['score' => 11])->assertStatus(422);
        $this->putJson("/api/v1/branches/{$branch->id}/review", ['score' => 8.5])->assertStatus(422);
    }

    public function test_rating_average_and_label(): void
    {
        $branch = $this->makeBranch('10.00');
        $staff = $this->makeStaff($branch);

        foreach ([10, 9, 7] as $score) {
            $customer = $this->makeCustomer();
            $this->earnAs($staff, $customer, '100')->assertCreated();
            Sanctum::actingAs($customer);
            $this->putJson("/api/v1/branches/{$branch->id}/review", ['score' => $score])->assertOk();
        }

        $this->getJson("/api/v1/branches/{$branch->id}")
            ->assertOk()
            ->assertJsonPath('data.rating.average', '8.7')
            ->assertJsonPath('data.rating.label', '8.7 / 10 — 3 değerlendirme')
            ->assertJsonPath('data.rating_distribution.10', 1);
    }

    public function test_branch_without_reviews_shows_placeholder(): void
    {
        $branch = $this->makeBranch('0.00');

        $this->getJson("/api/v1/branches/{$branch->id}")
            ->assertOk()
            ->assertJsonPath('data.rating.label', 'Henüz değerlendirme yok')
            ->assertJsonPath('data.reward_label', 'Ödül yok');
    }

    public function test_score_sort_requires_minimum_ten_reviews(): void
    {
        $few = $this->makeBranch('10.00');
        $few->forceFill(['rating_avg' => '9.9', 'rating_count' => 3])->save();
        $many = $this->makeBranch('10.00');
        $many->forceFill(['rating_avg' => '8.0', 'rating_count' => 50])->save();

        $ids = collect($this->getJson('/api/v1/branches?sort=score&per_page=50&district='.$this->kadikoy()->id)->json('data'))->pluck('id');

        $this->assertLessThan($ids->search($few->id), $ids->search($many->id));
    }

    public function test_unapproved_business_is_hidden(): void
    {
        $branch = $this->makeBranch();
        $branch->business->update(['status' => 'pending']);

        $this->getJson("/api/v1/branches/{$branch->id}")->assertStatus(404);
        $ids = collect($this->getJson('/api/v1/branches?per_page=50&q='.urlencode($branch->business->name))->json('data'))->pluck('id');
        $this->assertNotContains($branch->id, $ids);
    }

    public function test_lookup_endpoints(): void
    {
        $this->getJson('/api/v1/cities')->assertOk()->assertJsonCount(81, 'data');
        $this->getJson('/api/v1/cities/34/districts')->assertOk()->assertJsonCount(39, 'data');

        $bilecik = collect($this->getJson('/api/v1/cities/11/districts')->json('data'))->pluck('name');
        $this->assertContains('Bilecik Merkez', $bilecik);
    }

    public function test_home_and_listing(): void
    {
        $branch = $this->makeBranch('25.00');

        $this->getJson('/api/v1/home?lat=40.99&lng=29.03')
            ->assertOk()
            ->assertJsonStructure(['data' => ['sponsored', 'nearby', 'top_rate', 'with_campaign', 'newest']]);

        $nearest = $this->getJson('/api/v1/branches?sort=nearest&lat=40.99&lng=29.03&radius_km=5')->assertOk();
        $this->assertContains($branch->id, collect($nearest->json('data'))->pluck('id'));
        $this->assertNotNull($nearest->json('data.0.location.distance_km'));
    }

    public function test_database_blocks_ledger_and_customer_number_changes(): void
    {
        $branch = $this->makeBranch('10.00');
        $customer = $this->makeCustomer();
        $txId = $this->earnAs($this->makeStaff($branch), $customer, '100')->json('data.id');

        $this->assertDatabaseBlocks(fn () => DB::table('wallet_transactions')->where('id', $txId)->update(['amount' => '999.00']));
        $this->assertDatabaseBlocks(fn () => DB::table('wallet_transactions')->where('id', $txId)->delete());
        $this->assertDatabaseBlocks(fn () => DB::table('customers')->where('id', $customer->id)->update(['customer_no' => 999999999]));
        $this->assertDatabaseBlocks(fn () => DB::table('wallets')->where('customer_id', $customer->id)->update(['balance' => '-1.00']));
    }

    private function assertDatabaseBlocks(callable $fn): void
    {
        try {
            DB::transaction($fn); // savepoint: hata dış test transaction'ını bozmaz
            $this->fail('Veritabanı bu değişikliği engellemeliydi.');
        } catch (QueryException $e) {
            $this->assertTrue(true);
        }
    }
}
