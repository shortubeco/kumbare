<?php

namespace Tests\Feature;

use App\Domain\Billing\PlanResolver;
use App\Models\Branch;
use App\Models\PanelUser;
use App\Models\PaymentNotification;
use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Şube yöneticisi: Paket ve ödeme, Sponsorluk, Havale bildirimi. */
class BranchBillingTest extends TestCase
{
    private function setPrices(): void
    {
        DB::table('plans')->where('code', 'pro')->update(['monthly_price' => '500.00', 'yearly_price' => '4500.00']);
        Setting::put('sponsorship_monthly_price', '750');
        Setting::put('iban', 'TR120006100519786457841326');
        Setting::put('bank_name', 'Örnek Bank');
        Setting::put('account_holder', 'Kumbare Ltd.');
    }

    private function subscriptionForm(array $o = []): array
    {
        return array_merge([
            'purpose' => 'subscription', 'billing_cycle' => 'monthly', 'amount' => '500,00',
            'sender_name' => 'Deneme Gıda Ltd.', 'transfer_date' => now()->toDateString(),
        ], $o);
    }

    public function test_manager_lands_on_package_page_and_others_cannot(): void
    {
        $this->setPrices();
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);

        $this->post('/panel/giris', ['email' => $manager->email, 'password' => 'secret-password'])->assertRedirect(route('panel.home'));
        $this->get('/panel')->assertRedirect(route('panel.tx.screen'));
        $this->get('/panel/paket')->assertOk()
            ->assertSee('Paket ve ödeme')->assertSee('0 / 100', false)
            ->assertSee('TR12 0006 1005 1978 6457 8413 26')->assertSee('500,00 TL');
        $this->get('/panel/sponsorluk')->assertOk()->assertSee('750,00 TL');
        $this->get('/panel/havale-bildirimi')->assertOk()->assertSee('Bildirimi gönder');

        // Çalışan bu ekranlara giremez
        auth()->logout();
        $staff = $this->makeStaff($branch);
        $this->actingAs($staff);
        $this->get('/panel')->assertRedirect(route('panel.tx.screen')); // çalışan işlem ekranına gider
        $this->get('/panel/paket')->assertForbidden();

        // Girişsiz
        auth()->logout();
        $this->get('/panel/paket')->assertRedirect(route('panel.login'));
    }

    public function test_subscription_notification_with_receipt_then_admin_approval(): void
    {
        Storage::fake('local');
        $this->setPrices();
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        $this->actingAs($manager);

        // Doğrulama hataları
        $this->post('/panel/havale-bildirimi', $this->subscriptionForm(['amount' => 'abc']))->assertSessionHasErrors('amount');
        // Web formlarında da hata mesajları büyük harfle başlar
        $this->post('/panel/havale-bildirimi', $this->subscriptionForm(['amount' => '']))
            ->assertSessionHasErrors(['amount' => 'Tutar alanı zorunludur.']);
        $this->post('/panel/havale-bildirimi', $this->subscriptionForm(['transfer_date' => now()->addDay()->toDateString()]))->assertSessionHasErrors('transfer_date');
        $this->post('/panel/havale-bildirimi', $this->subscriptionForm(['billing_cycle' => null]))->assertSessionHasErrors('billing_cycle');
        $this->post('/panel/havale-bildirimi', $this->subscriptionForm(['receipt' => UploadedFile::fake()->create('virus.exe', 10)]))->assertSessionHasErrors('receipt');
        $this->assertSame(0, PaymentNotification::query()->where('branch_id', $branch->id)->count());

        $this->post('/panel/havale-bildirimi', $this->subscriptionForm([
            'amount' => '1.234,50',
            'receipt' => UploadedFile::fake()->create('dekont.pdf', 120, 'application/pdf'),
        ]))->assertRedirect(route('panel.package'))->assertSessionHas('status');

        $payment = PaymentNotification::query()->where('branch_id', $branch->id)->firstOrFail();
        $this->assertSame('pending', $payment->status);
        $this->assertSame('1234.50', (string) $payment->amount);
        $this->assertSame($manager->id, $payment->submitted_by);
        Storage::disk('local')->assertExists($payment->receipt_path);
        $this->assertStringStartsWith('dekontlar/'.$branch->id.'/', $payment->receipt_path);

        // Bekleyen varken ikinci paket bildirimi gönderilemez
        $this->post('/panel/havale-bildirimi', $this->subscriptionForm())->assertSessionHas('error');
        $this->assertSame(1, PaymentNotification::query()->where('branch_id', $branch->id)->count());

        $this->get('/panel/paket')->assertSee('onay bekliyor')->assertSee('1.234,50 TL', false);
        $this->get(route('panel.payment.receipt', $payment))->assertOk();

        // Başka şubenin yöneticisi bu dekontu açamaz
        $other = $this->makeManager($this->makeBranch());
        $this->actingAs($other)->get(route('panel.payment.receipt', $payment))->assertNotFound();

        // Platform yöneticisi onaylar → şube Profesyonel
        $admin = PanelUser::query()->create([
            'role' => PanelUser::ROLE_PLATFORM_ADMIN, 'first_name' => 'P', 'last_name' => 'Y'.$this->next(),
            'email' => 'adm'.$this->next().'@test.local', 'password' => 'secret-password-123', 'status' => 'active',
        ]);
        $this->actingAs($admin)->get('/yonetim/havaleler')->assertSee($branch->name);
        $this->get('/yonetim/havaleler/'.$payment->id)->assertOk()->assertSee('Tutar farklı');
        $this->get(route('admin.payments.receipt', $payment))->assertOk();
        $this->post(route('admin.payments.approve', $payment))->assertSessionHas('status');
        $this->assertTrue(app(PlanResolver::class)->isPro($branch));

        $this->actingAs($manager)->get('/panel/paket')->assertSee('Onaylandı')->assertSee('gün kaldı');
    }

    public function test_sponsorship_notification_month_rules(): void
    {
        $this->setPrices();
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        $this->actingAs($manager);

        $thisMonth = now()->startOfMonth()->toDateString();
        $past = now()->startOfMonth()->subMonthNoOverflow()->toDateString();
        $form = fn (string $m) => [
            'purpose' => 'sponsorship', 'sponsorship_month' => $m, 'amount' => '750',
            'sender_name' => 'Deneme', 'transfer_date' => now()->toDateString(),
        ];

        $this->post('/panel/havale-bildirimi', $form($past))->assertSessionHasErrors('sponsorship_month');
        $this->post('/panel/havale-bildirimi', $form($thisMonth))->assertRedirect(route('panel.sponsorship'));
        $this->assertDatabaseHas('payment_notifications', ['branch_id' => $branch->id, 'purpose' => 'sponsorship', 'billing_cycle' => null, 'status' => 'pending']);

        // Aynı ay için ikinci bildirim engellenir; sayfada "onay bekliyor" görünür
        $this->post('/panel/havale-bildirimi', $form($thisMonth))->assertSessionHas('error');
        $this->get('/panel/sponsorluk')->assertSee('Bildirim onay bekliyor');

        // Paket bildirimi sponsorluktan bağımsız gönderilebilir
        $this->post('/panel/havale-bildirimi', $this->subscriptionForm())->assertSessionHas('status');
    }

    public function test_suspended_business_manager_sees_closed_message(): void
    {
        $branch = $this->makeBranch();
        $manager = $this->makeManager($branch);
        $branch->business->update(['status' => 'suspended']);

        $this->actingAs($manager)->get('/panel/paket')->assertRedirect(route('panel.home'));
        $this->get('/panel')->assertOk()->assertSee('kullanıma kapalı');
    }
}
