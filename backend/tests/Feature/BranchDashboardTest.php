<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Şube yöneticisi Genel bakış ekranı. */
class BranchDashboardTest extends TestCase
{
    public function test_manager_dashboard_shows_only_own_branch_figures(): void
    {
        $branch = $this->makeBranch('10.00');
        $manager = $this->makeManager($branch);
        $staff = $this->makeStaff($branch);
        $customer = $this->makeCustomer(['first_name' => 'Zeynep', 'last_name' => 'Kaya']);
        $this->earnAs($staff, $customer, '1000.00'); // 100 TL ödül

        // Başka şubedeki hareket bu ekrana girmez
        $other = $this->makeBranch('10.00');
        $this->earnAs($this->makeStaff($other), $this->makeCustomer(['first_name' => 'Başkası']), '5000.00');

        $this->actingAs($manager, 'web')->get('/panel/genel-bakis')->assertOk()
            ->assertSee('Genel bakış')
            ->assertSee('1 / 100', false)            // bu ayın müşterisi / limit
            ->assertSee('100,00 TL', false)          // bu ay kazandırılan
            ->assertSee('Zeynep Kaya')               // en aktif müşteriler
            ->assertDontSee('Başkası')
            ->assertSee('Günlük işlem sayısı')
            ->assertSee('<b>1</b> yeni', false);
    }

    public function test_limit_warning_and_pro_badge(): void
    {
        $branch = $this->makeBranch('10.00');
        $manager = $this->makeManager($branch);
        $staff = $this->makeStaff($branch);
        $this->setLimit(1);
        $this->earnAs($staff, $this->makeCustomer(), '100.00');

        $this->actingAs($manager, 'web')->get('/panel/genel-bakis')->assertOk()->assertSee('Limit doldu');

        $this->makePro($branch);
        $this->get('/panel/genel-bakis')->assertOk()->assertSee('sınır yok')->assertSee('gün kaldı');
    }

    public function test_only_branch_manager_can_open(): void
    {
        $branch = $this->makeBranch();
        $this->actingAs($this->makeStaff($branch))->get('/panel/genel-bakis')->assertForbidden();
        auth()->logout();
        $this->get('/panel/genel-bakis')->assertRedirect(route('panel.login'));
    }
}
