<?php

use App\Http\Controllers\Api\Panel\AuthController as PanelAuthController;
use App\Http\Controllers\Api\Panel\TransactionController;
use App\Http\Controllers\Api\V1\ApprovalController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\CampaignController;
use App\Http\Controllers\Api\V1\FavoriteController;
use App\Http\Controllers\Api\V1\HomeController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\WalletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobil API (müşteri) — /api/v1
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function () {
    Route::middleware('throttle:auth')->group(function () {
        Route::post('auth/register', [AuthController::class, 'register']);
        Route::post('auth/login', [AuthController::class, 'login']);
        Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('auth/reset-password', [AuthController::class, 'resetPassword']);
    });

    // Herkese açık keşif uç noktaları
    Route::get('cities', [LookupController::class, 'cities']);
    Route::get('cities/{city}/districts', [LookupController::class, 'districts'])->whereNumber('city');
    Route::get('categories', [LookupController::class, 'categories']);
    Route::get('home', HomeController::class);
    Route::get('branches', [BranchController::class, 'index']);
    Route::get('branches/{branch}', [BranchController::class, 'show'])->whereNumber('branch');
    Route::get('branches/{branch}/reviews', [BranchController::class, 'reviews'])->whereNumber('branch');

    Route::middleware(['auth:sanctum', 'customer'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('me', [MeController::class, 'show']);
        Route::put('me', [MeController::class, 'update']);
        Route::put('me/password', [MeController::class, 'changePassword']);
        Route::delete('me', [MeController::class, 'destroy']);
        Route::get('me/reviews', [MeController::class, 'reviews']);

        Route::get('wallets', [WalletController::class, 'index']);
        Route::get('wallets/{wallet}', [WalletController::class, 'show'])->whereNumber('wallet');
        Route::get('wallets/{wallet}/transactions', [WalletController::class, 'transactions'])->whereNumber('wallet');

        // Bildirimler: onay bekleyen ödül kullanım ve düzeltme istekleri
        Route::get('approvals', [ApprovalController::class, 'index']);
        Route::post('approvals/{approvalRequest}/approve', [ApprovalController::class, 'approve'])->whereNumber('approvalRequest');
        Route::post('approvals/{approvalRequest}/reject', [ApprovalController::class, 'reject'])->whereNumber('approvalRequest');

        Route::get('campaigns', [CampaignController::class, 'index']);

        Route::get('favorites', [FavoriteController::class, 'index']);
        Route::post('favorites/{branch}', [FavoriteController::class, 'store'])->whereNumber('branch');
        Route::delete('favorites/{branch}', [FavoriteController::class, 'destroy'])->whereNumber('branch');

        Route::put('branches/{branch}/review', [ReviewController::class, 'upsert'])->whereNumber('branch');
        Route::post('reviews/{review}/report', [ReviewController::class, 'report'])->whereNumber('review');
    });
});

/*
|--------------------------------------------------------------------------
| Personel işlem API'si — /api/panel/v1
| (Şube yöneticisi ve çalışanlar; web panelinin işlem ekranı da bunu kullanacak.)
|--------------------------------------------------------------------------
*/
Route::prefix('panel/v1')->group(function () {
    Route::post('auth/login', [PanelAuthController::class, 'login'])->middleware('throttle:auth');

    Route::middleware(['auth:sanctum', 'panel'])->group(function () {
        Route::post('auth/logout', [PanelAuthController::class, 'logout']);
        Route::get('me', [PanelAuthController::class, 'me']);

        Route::get('customers/{customerNo}', [TransactionController::class, 'lookup'])
            ->whereNumber('customerNo')->middleware('throttle:customer-lookup');
        Route::get('customers/{customerNo}/campaigns', [TransactionController::class, 'campaigns'])->whereNumber('customerNo');
        Route::get('customers/{customerNo}/transactions', [TransactionController::class, 'history'])->whereNumber('customerNo');

        Route::post('earn/preview', [TransactionController::class, 'earnPreview']);
        Route::post('earn', [TransactionController::class, 'earn']);
        Route::post('redeem', [TransactionController::class, 'redeem']);

        Route::get('approval-requests/{approvalRequest}', [TransactionController::class, 'showRequest'])->whereNumber('approvalRequest');
        Route::post('approval-requests/{approvalRequest}/withdraw', [TransactionController::class, 'withdraw'])->whereNumber('approvalRequest');

        Route::post('corrections', [TransactionController::class, 'correction']);
    });
});
