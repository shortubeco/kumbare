<?php

use App\Http\Controllers\Admin\AuditLogController as AdminAudit;
use App\Http\Controllers\Admin\BusinessController as AdminBusinesses;
use App\Http\Controllers\Admin\CategoryController as AdminCategories;
use App\Http\Controllers\Admin\CustomerController as AdminCustomers;
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\ModerationController as AdminModeration;
use App\Http\Controllers\Admin\PaymentController as AdminPayments;
use App\Http\Controllers\Admin\SettingsController as AdminSettings;
use App\Http\Controllers\Admin\SubscriptionController as AdminSubscriptions;
use App\Http\Controllers\Api\Panel\TransactionController;
use App\Http\Controllers\Panel\BillingController;
use App\Http\Controllers\Panel\Business\BranchController as BizBranches;
use App\Http\Controllers\Panel\Business\ManagerController as BizManagers;
use App\Http\Controllers\Panel\Business\MenuController as BizMenu;
use App\Http\Controllers\Panel\Business\OverviewController as BizOverview;
use App\Http\Controllers\Panel\Business\ReportController as BizReports;
use App\Http\Controllers\Panel\BranchDashboardController;
use App\Http\Controllers\Panel\BranchSettingsController as PanelSettings;
use App\Http\Controllers\Panel\CampaignController as PanelCampaigns;
use App\Http\Controllers\Panel\CorrectionController as PanelCorrections;
use App\Http\Controllers\Panel\NotificationController as PanelNotifications;
use App\Http\Controllers\Panel\ReportController as PanelReports;
use App\Http\Controllers\Panel\ReviewController as PanelReviews;
use App\Http\Controllers\Panel\StaffController as PanelStaff;
use App\Http\Controllers\Panel\CustomerController as PanelCustomers;
use App\Http\Controllers\Panel\TransactionScreenController;
use App\Http\Controllers\Panel\LoginController;
use App\Http\Controllers\Web\BranchController;
use App\Http\Controllers\Web\BranchListController;
use App\Http\Controllers\Web\BusinessApplicationController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\PasswordResetController;
use App\Http\Controllers\Web\SeoController;
use Illuminate\Support\Facades\Route;

/*
| Herkese açık web sitesi.
| WEB_DOMAIN tanımlıysa site yalnızca o alan adında açılır; diğer alan adlarında
| (ör. API alt alan adı) ana adres API bilgisini döndürür.
*/

Route::get('robots.txt', [SeoController::class, 'robots']);

$site = function () {
    Route::get('/', HomeController::class)->name('web.home');

    Route::get('restoranlar', [BranchListController::class, 'index'])->name('web.branches');
    Route::get('restoranlar/{il}', [BranchListController::class, 'city'])->where('il', '[a-z0-9-]+')->name('web.city');
    Route::get('restoranlar/{il}/{ilce}', [BranchListController::class, 'district'])
        ->where(['il' => '[a-z0-9-]+', 'ilce' => '[a-z0-9-]+'])->name('web.district');
    Route::get('kategori/{mutfak}', [BranchListController::class, 'category'])->where('mutfak', '[a-z0-9-]+')->name('web.category');
    Route::get('restoran/{isletme}/{sube}', [BranchController::class, 'show'])
        ->where(['isletme' => '[a-z0-9-]+', 'sube' => '[a-z0-9-]+'])->name('web.branch');

    Route::get('isletmeler-icin', [BusinessApplicationController::class, 'show'])->name('web.business');
    Route::post('isletmeler-icin/basvuru', [BusinessApplicationController::class, 'store'])
        ->middleware('throttle:5,1')->name('web.business.apply');
    Route::get('isletmeler-icin/basvuru-alindi', [BusinessApplicationController::class, 'thanks'])->name('web.business.thanks');

    Route::get('sifre-sifirla', [PasswordResetController::class, 'show'])->name('web.password.show');
    Route::post('sifre-sifirla', [PasswordResetController::class, 'update'])->middleware('throttle:10,1')->name('web.password.update');
    Route::get('panel/sifre-sifirla', [PasswordResetController::class, 'show'])->name('web.panel.password.show');
    Route::post('panel/sifre-sifirla', [PasswordResetController::class, 'update'])->middleware('throttle:10,1')->name('web.panel.password.update');

    Route::view('kvkk-aydinlatma-metni', 'site.kvkk', [
        'seo' => ['title' => 'KVKK Aydınlatma Metni | Kumbare', 'description' => 'Kumbare kişisel verilerin korunması aydınlatma metni.', 'canonical' => null, 'robots' => 'index,follow'],
    ])->name('web.kvkk');

    Route::get('sitemap.xml', [SeoController::class, 'sitemap'])->name('web.sitemap');

    /*
    | Panel girişi (tüm panel kullanıcıları) ve platform yönetimi (/yonetim)
    */
    Route::get('panel/giris', [LoginController::class, 'show'])->name('panel.login');
    Route::post('panel/giris', [LoginController::class, 'login'])->middleware('throttle:10,1')->name('panel.login.submit');
    Route::post('panel/cikis', [LoginController::class, 'logout'])->name('panel.logout');
    Route::get('panel/sifremi-unuttum', [LoginController::class, 'forgotForm'])->name('panel.forgot');
    Route::post('panel/sifremi-unuttum', [LoginController::class, 'forgotSend'])->middleware('throttle:5,1')->name('panel.forgot.send');
    Route::get('panel', [LoginController::class, 'home'])->name('panel.home');

    // İşlem ekranı (şube yöneticisi + çalışan). Arka plan istekleri personel API'siyle aynı
    // denetleyiciyi kullanır (oturum tabanlı); yetkiler StaffGate'te kontrol edilir.
    Route::prefix('panel/islem')->middleware('panel.staff')->name('panel.tx.')->group(function () {
        Route::get('/', [TransactionScreenController::class, 'show'])->name('screen');
        Route::get('musteri/{customerNo}', [TransactionController::class, 'lookup'])
            ->whereNumber('customerNo')->middleware('throttle:customer-lookup')->name('lookup');
        Route::get('musteri/{customerNo}/kampanyalar', [TransactionController::class, 'campaigns'])->whereNumber('customerNo')->name('campaigns');
        Route::post('kazandir/ozet', [TransactionController::class, 'earnPreview'])->name('earn.preview');
        Route::post('kazandir', [TransactionController::class, 'earn'])->name('earn');
        Route::post('kullandir', [TransactionController::class, 'redeem'])->name('redeem');
        Route::get('istek/{approvalRequest}', [TransactionController::class, 'showRequest'])->whereNumber('approvalRequest')->name('request');
        Route::post('istek/{approvalRequest}/geri-cek', [TransactionController::class, 'withdraw'])->whereNumber('approvalRequest')->name('withdraw');
    });

    // İşletme (marka) yöneticisi paneli
    Route::prefix('panel/isletme')->middleware('business.owner')->name('biz.')->group(function () {
        Route::get('/', [BizOverview::class, 'index'])->name('overview');
        Route::get('bilgiler', [BizOverview::class, 'profile'])->name('profile');
        Route::post('bilgiler', [BizOverview::class, 'updateProfile'])->name('profile.update');

        Route::get('subeler', [BizBranches::class, 'index'])->name('branches');
        Route::get('subeler/yeni', [BizBranches::class, 'create'])->name('branches.create');
        Route::post('subeler', [BizBranches::class, 'store'])->name('branches.store');
        Route::get('subeler/{branch}', [BizBranches::class, 'show'])->whereNumber('branch')->name('branches.show');
        Route::post('subeler/{branch}/durum', [BizBranches::class, 'updateStatus'])->whereNumber('branch')->name('branches.status');
        Route::post('subeler/{branch}/sil', [BizBranches::class, 'destroy'])->whereNumber('branch')->name('branches.destroy');
        Route::get('ilceler/{city}', [BizBranches::class, 'districts'])->whereNumber('city')->name('districts');

        Route::get('sube-yoneticileri', [BizManagers::class, 'index'])->name('managers');
        Route::get('sube-yoneticileri/yeni', [BizManagers::class, 'create'])->name('managers.create');
        Route::post('sube-yoneticileri', [BizManagers::class, 'store'])->name('managers.store');
        Route::get('sube-yoneticileri/{manager}/duzenle', [BizManagers::class, 'edit'])->whereNumber('manager')->name('managers.edit');
        Route::post('sube-yoneticileri/{manager}', [BizManagers::class, 'update'])->whereNumber('manager')->name('managers.update');
        Route::post('sube-yoneticileri/{manager}/durum', [BizManagers::class, 'toggle'])->whereNumber('manager')->name('managers.toggle');

        Route::get('menu', [BizMenu::class, 'index'])->name('menu');
        Route::post('menu/kategoriler', [BizMenu::class, 'storeCategory'])->name('menu.categories.store');
        Route::post('menu/kategoriler/{category}', [BizMenu::class, 'updateCategory'])->whereNumber('category')->name('menu.categories.update');
        Route::post('menu/kategoriler/{category}/sil', [BizMenu::class, 'deleteCategory'])->whereNumber('category')->name('menu.categories.delete');
        Route::post('menu/kategoriler/{category}/tasi', [BizMenu::class, 'moveCategory'])->whereNumber('category')->name('menu.categories.move');
        Route::get('menu/urunler/yeni', [BizMenu::class, 'createItem'])->name('menu.items.create');
        Route::post('menu/urunler', [BizMenu::class, 'storeItem'])->name('menu.items.store');
        Route::get('menu/urunler/{item}/duzenle', [BizMenu::class, 'editItem'])->whereNumber('item')->name('menu.items.edit');
        Route::post('menu/urunler/{item}', [BizMenu::class, 'updateItem'])->whereNumber('item')->name('menu.items.update');
        Route::post('menu/urunler/{item}/satis', [BizMenu::class, 'toggleItem'])->whereNumber('item')->name('menu.items.toggle');
        Route::post('menu/urunler/{item}/sil', [BizMenu::class, 'deleteItem'])->whereNumber('item')->name('menu.items.delete');
        Route::post('menu/urunler/{item}/tasi', [BizMenu::class, 'moveItem'])->whereNumber('item')->name('menu.items.move');

        Route::get('raporlar', [BizReports::class, 'index'])->name('reports');
        Route::get('abonelikler', [BizReports::class, 'subscriptions'])->name('subscriptions');
    });

    // Müşteriler: şube yöneticisi + "müşterileri görüntüleme" yetkili çalışan (yetki denetleyicide)
    Route::prefix('panel')->middleware('panel.staff')->name('panel.')->group(function () {
        Route::get('musteriler', [PanelCustomers::class, 'index'])->name('customers');
        Route::get('musteriler/{customerNo}', [PanelCustomers::class, 'show'])->whereNumber('customerNo')->name('customers.show');
        Route::get('raporlar', [PanelReports::class, 'index'])->name('reports');
        Route::get('bildirimler', [PanelNotifications::class, 'index'])->name('notifications');
        Route::post('bildirimler/okundu', [PanelNotifications::class, 'readAll'])->name('notifications.read');
    });

    // Şube yöneticisi: paket, sponsorluk, havale bildirimi, düzeltmeler
    Route::prefix('panel')->middleware('branch.manager')->name('panel.')->group(function () {
        Route::get('genel-bakis', BranchDashboardController::class)->name('dashboard');
        Route::get('duzeltmeler', [PanelCorrections::class, 'index'])->name('corrections');
        Route::get('duzeltmeler/yeni', [PanelCorrections::class, 'create'])->name('corrections.create');
        Route::post('duzeltmeler', [PanelCorrections::class, 'store'])->middleware('throttle:20,1')->name('corrections.store');
        Route::post('duzeltmeler/{approvalRequest}/geri-cek', [PanelCorrections::class, 'withdraw'])->whereNumber('approvalRequest')->name('corrections.withdraw');

        // Şube ayarları
        Route::get('odul-orani', [PanelSettings::class, 'rate'])->name('rate');
        Route::post('odul-orani', [PanelSettings::class, 'updateRate'])->name('rate.update');
        Route::get('sube-bilgileri', [PanelSettings::class, 'info'])->name('info');
        Route::post('sube-bilgileri', [PanelSettings::class, 'updateInfo'])->name('info.update');
        Route::post('sube-bilgileri/fotograflar', [PanelSettings::class, 'uploadPhotos'])->middleware('throttle:20,1')->name('photos.upload');
        Route::post('sube-bilgileri/fotograflar/{photo}/sil', [PanelSettings::class, 'deletePhoto'])->whereNumber('photo')->name('photos.delete');
        Route::post('sube-bilgileri/fotograflar/{photo}/kapak', [PanelSettings::class, 'coverPhoto'])->whereNumber('photo')->name('photos.cover');

        Route::get('kampanyalar', [PanelCampaigns::class, 'index'])->name('campaigns');
        Route::get('kampanyalar/yeni', [PanelCampaigns::class, 'create'])->name('campaigns.create');
        Route::post('kampanyalar', [PanelCampaigns::class, 'store'])->name('campaigns.store');
        Route::get('kampanyalar/{campaign}/duzenle', [PanelCampaigns::class, 'edit'])->whereNumber('campaign')->name('campaigns.edit');
        Route::post('kampanyalar/{campaign}', [PanelCampaigns::class, 'update'])->whereNumber('campaign')->name('campaigns.update');
        Route::post('kampanyalar/{campaign}/bitir', [PanelCampaigns::class, 'end'])->whereNumber('campaign')->name('campaigns.end');

        Route::get('calisanlar', [PanelStaff::class, 'index'])->name('staff');
        Route::get('calisanlar/yeni', [PanelStaff::class, 'create'])->name('staff.create');
        Route::post('calisanlar', [PanelStaff::class, 'store'])->name('staff.store');
        Route::get('calisanlar/{staff}/duzenle', [PanelStaff::class, 'edit'])->whereNumber('staff')->name('staff.edit');
        Route::post('calisanlar/{staff}', [PanelStaff::class, 'update'])->whereNumber('staff')->name('staff.update');
        Route::post('calisanlar/{staff}/durum', [PanelStaff::class, 'toggle'])->whereNumber('staff')->name('staff.toggle');

        Route::get('degerlendirmeler', [PanelReviews::class, 'index'])->name('reviews');
        Route::post('degerlendirmeler/{review}/cevap', [PanelReviews::class, 'reply'])->whereNumber('review')->name('reviews.reply');
        Route::post('degerlendirmeler/{review}/sikayet', [PanelReviews::class, 'report'])->whereNumber('review')->name('reviews.report');
        Route::get('paket', [BillingController::class, 'package'])->name('package');
        Route::get('sponsorluk', [BillingController::class, 'sponsorship'])->name('sponsorship');
        Route::get('havale-bildirimi', [BillingController::class, 'create'])->name('payment.create');
        Route::post('havale-bildirimi', [BillingController::class, 'store'])->middleware('throttle:10,1')->name('payment.store');
        Route::get('havale-bildirimi/{payment}/dekont', [BillingController::class, 'receipt'])->whereNumber('payment')->name('payment.receipt');
    });

    Route::prefix('yonetim')->middleware('platform')->name('admin.')->group(function () {
        Route::get('/', AdminDashboard::class)->name('dashboard');

        Route::get('isletmeler', [AdminBusinesses::class, 'index'])->name('businesses');
        Route::get('isletmeler/{business}', [AdminBusinesses::class, 'show'])->whereNumber('business')->name('businesses.show');
        Route::post('isletmeler/{business}/onayla', [AdminBusinesses::class, 'approve'])->whereNumber('business')->name('businesses.approve');
        Route::post('isletmeler/{business}/reddet', [AdminBusinesses::class, 'reject'])->whereNumber('business')->name('businesses.reject');
        Route::post('isletmeler/{business}/durum', [AdminBusinesses::class, 'updateStatus'])->whereNumber('business')->name('businesses.status');
        Route::post('subeler/{branch}/durum', [AdminBusinesses::class, 'updateBranchStatus'])->whereNumber('branch')->name('branches.status');

        Route::get('musteriler', [AdminCustomers::class, 'index'])->name('customers');
        Route::get('musteriler/{customer}', [AdminCustomers::class, 'show'])->whereNumber('customer')->name('customers.show');
        Route::post('musteriler/{customer}/durum', [AdminCustomers::class, 'updateStatus'])->whereNumber('customer')->name('customers.status');
        Route::post('musteriler/{customer}/dogum-tarihi', [AdminCustomers::class, 'updateBirthDate'])->whereNumber('customer')->name('customers.birth-date');

        Route::get('havaleler', [AdminPayments::class, 'index'])->name('payments');
        Route::get('havaleler/{payment}', [AdminPayments::class, 'show'])->whereNumber('payment')->name('payments.show');
        Route::get('havaleler/{payment}/dekont', [AdminPayments::class, 'receipt'])->whereNumber('payment')->name('payments.receipt');
        Route::post('havaleler/{payment}/onayla', [AdminPayments::class, 'approve'])->whereNumber('payment')->name('payments.approve');
        Route::post('havaleler/{payment}/reddet', [AdminPayments::class, 'reject'])->whereNumber('payment')->name('payments.reject');

        Route::get('abonelikler', [AdminSubscriptions::class, 'index'])->name('subscriptions');
        Route::post('abonelikler/{subscription}/iptal', [AdminSubscriptions::class, 'cancel'])->whereNumber('subscription')->name('subscriptions.cancel');
        Route::get('sponsorluklar', [AdminSubscriptions::class, 'sponsorships'])->name('sponsorships');
        Route::post('sponsorluklar/{sponsorship}/iptal', [AdminSubscriptions::class, 'cancelSponsorship'])->whereNumber('sponsorship')->name('sponsorships.cancel');

        Route::get('kampanyalar', [AdminModeration::class, 'campaigns'])->name('campaigns');
        Route::post('kampanyalar/{campaign}/kaldir', [AdminModeration::class, 'removeCampaign'])->whereNumber('campaign')->name('campaigns.remove');
        Route::get('sikayetler', [AdminModeration::class, 'reports'])->name('reports');
        Route::post('yorumlar/{review}/kaldir', [AdminModeration::class, 'removeReview'])->whereNumber('review')->name('reviews.remove');
        Route::post('yorumlar/{review}/sikayetleri-kapat', [AdminModeration::class, 'dismissReports'])->whereNumber('review')->name('reviews.dismiss');

        Route::get('kategoriler', [AdminCategories::class, 'index'])->name('categories');
        Route::post('kategoriler', [AdminCategories::class, 'store'])->name('categories.store');
        Route::post('kategoriler/{category}', [AdminCategories::class, 'update'])->whereNumber('category')->name('categories.update');

        Route::get('ayarlar', [AdminSettings::class, 'edit'])->name('settings');
        Route::post('ayarlar', [AdminSettings::class, 'update'])->name('settings.update');

        Route::get('denetim', [AdminAudit::class, 'index'])->name('audit');
        Route::get('denetim/numara-sorgulari', [AdminAudit::class, 'lookups'])->name('audit.lookups');
    });
};

if ($webDomain = config('app.web_domain')) {
    Route::domain($webDomain)->group($site);

    // Diğer alan adlarında (API) ana adres yalnızca API bilgisini döndürür.
    Route::get('/', fn () => response()->json(['name' => 'Kumbare API', 'version' => 'v1', 'status' => 'ok']));
} else {
    $site();
}
