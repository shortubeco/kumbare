<?php

namespace Tests\Feature;

use App\Domain\Reports\BranchReport;
use App\Models\PanelUser;
use App\Models\WalletTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Şube raporları. */
class BranchReportTest extends TestCase
{
    public function test_report_figures_new_vs_returning_and_campaigns(): void
    {
        $branch = $this->makeBranch('10.00');
        $staff = $this->makeStaff($branch);
        $old = $this->makeCustomer(['first_name' => 'Eski', 'last_name' => 'Müşteri']);
        $new = $this->makeCustomer(['first_name' => 'Yeni', 'last_name' => 'Müşteri']);

        // "Eski" müşterinin ilk kazanımı 40 gün önce (işlem defteri değiştirilemediği için zamanda geri gidilir)
        $this->travel(-40)->days();
        $this->earnAs($staff, $old, '500.00');
        $this->travelBack();
        // Bu dönem: ikisi de kazanır, biri kampanyayla
        $campaign = $this->makeRateCampaign($branch, '20.00');
        $this->earnAs($staff, $old, '1000.00');
        $this->earnAs($staff, $new, '200.00', $campaign->id);

        $r = new BranchReport($branch, CarbonImmutable::today()->subDays(29), CarbonImmutable::today());
        $s = $r->summary();
        $this->assertSame(2, (int) $s->customers);
        $this->assertSame(1, $s->new_customers);
        $this->assertSame(1, $s->returning_customers);
        $this->assertSame('1200.00', (string) $s->spend);
        $this->assertSame('140.00', (string) $s->earned);     // 100 + 40
        $this->assertSame('190.00', (string) $s->balance);    // 50 + 100 + 40
        $this->assertSame('day', $r->granularity());
        $this->assertCount(30, $r->periods());

        $campaigns = collect($r->campaigns());
        $this->assertSame('40.00', (string) $campaigns->firstWhere('id', $campaign->id)->earned);
        $this->assertSame('100.00', (string) $campaigns->firstWhere('id', null)->earned);

        // 90 gün → haftalık, 1 yıl → aylık
        $this->assertSame('week', (new BranchReport($branch, CarbonImmutable::today()->subDays(89), CarbonImmutable::today()))->granularity());
        $this->assertSame('month', (new BranchReport($branch, CarbonImmutable::today()->subDays(300), CarbonImmutable::today()))->granularity());
        $weekly = (new BranchReport($branch, CarbonImmutable::today()->subDays(89), CarbonImmutable::today()))->periods();
        $this->assertSame(2, array_sum(array_column($weekly, 'new'))); // 40 gün önceki ilk kazanım da bu aralıkta
    }

    public function test_reports_page_presets_custom_range_and_csv(): void
    {
        $branch = $this->makeBranch('10.00');
        $manager = $this->makeManager($branch);
        $staff = $this->makeStaff($branch);
        $this->earnAs($staff, $this->makeCustomer(['first_name' => 'Çağla', 'last_name' => 'Öztürk']), '1234.50');
        $this->actingAs($manager, 'web');

        $this->get('/panel/raporlar')->assertOk()
            ->assertSee('Raporlar')->assertSee('Çağla Öztürk')->assertSee('123,45', false)->assertSee('Personel');
        foreach (array_keys(\App\Http\Controllers\Panel\ReportController::PRESETS) as $p) {
            $this->get('/panel/raporlar?aralik='.$p)->assertOk();
        }
        $this->get('/panel/raporlar?baslangic=2026-01-01&bitis=2026-01-31')->assertOk()->assertSee('31 gün');
        $this->get('/panel/raporlar?baslangic=2026-02-01&bitis=2026-01-01')->assertOk()->assertSee('Başlangıç tarihi bitişten sonra olamaz');
        $this->get('/panel/raporlar?baslangic=2024-01-01&bitis=2026-01-01')->assertOk()->assertSee('En fazla 366 günlük');

        $csv = $this->get('/panel/raporlar?aralik=30g&indir=musteriler');
        $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $body = $csv->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $this->assertStringContainsString('"Çağla Öztürk";1;1234,50;123,45', $body); // boşluklu alan tırnaklı (CSV standardı)
        $this->get('/panel/raporlar?indir=donemler')->assertOk();
        $this->get('/panel/raporlar?indir=kampanyalar')->assertOk();
        $this->get('/panel/raporlar?indir=bilinmeyen')->assertNotFound();
    }

    public function test_report_access_by_permission(): void
    {
        $branch = $this->makeBranch();
        $noPerm = $this->makeStaff($branch, [PanelUser::PERM_LOOKUP]);
        $this->actingAs($noPerm, 'web')->get('/panel/raporlar')->assertForbidden();
        $this->get('/panel/islem')->assertDontSee('>Raporlar<', false);

        $viewer = $this->makeStaff($branch, [PanelUser::PERM_VIEW_REPORTS]);
        $this->actingAs($viewer, 'web')->get('/panel/raporlar')->assertOk()->assertDontSee('<h2>Personel</h2>', false);
        $this->get('/panel/islem')->assertSee('Raporlar');

        // Başka şubenin verisi rapora girmez
        $other = $this->makeBranch('10.00');
        $this->earnAs($this->makeStaff($other), $this->makeCustomer(['first_name' => 'Başka', 'last_name' => 'Şube']), '100.00');
        $this->actingAs($viewer, 'web')->get('/panel/raporlar')->assertDontSee('Başka Şube');
        $this->assertTrue(WalletTransaction::query()->where('branch_id', $other->id)->exists());
    }
}
