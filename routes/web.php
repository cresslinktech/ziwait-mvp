<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Dashboard\BranchController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DirectoryController;
use App\Http\Controllers\Admin\PaymentReviewController;
use App\Http\Controllers\Admin\AdvertisementController;
use App\Http\Controllers\Dashboard\BillingController;
use App\Http\Controllers\Dashboard\BranchMenuController;
use App\Http\Controllers\Dashboard\MenuController;
use App\Http\Controllers\Dashboard\OrderController;
use App\Http\Controllers\Dashboard\CustomerController;
use App\Http\Controllers\Dashboard\NotificationController;
use App\Http\Controllers\Dashboard\OnboardingController;
use App\Http\Controllers\Dashboard\SettingsController;
use App\Http\Controllers\Dashboard\PushSubscriptionController;
use App\Http\Controllers\Dashboard\QrController;
use App\Http\Controllers\Public\MarketingController;
use App\Http\Controllers\Public\PublicMenuController;
use App\Http\Controllers\Webhooks\PaystackWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MarketingController::class, 'home'])->name('home');
Route::post('/contact', [MarketingController::class, 'contact'])->middleware('throttle:contact')->name('contact');

Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth');
});

// Public ordering: no account needed, branch resolved only from the revocable QR token.
Route::get('/m/{token}', [PublicMenuController::class, 'show'])->name('menu.show');
Route::get('/m/{token}/orders', [PublicMenuController::class, 'orders'])->name('menu.orders');
Route::post('/m/{token}/order', [PublicMenuController::class, 'store'])->middleware('throttle:order')->name('menu.order');
Route::get('/order/{publicToken}', [PublicMenuController::class, 'confirmation'])->name('order.confirmation');

Route::post('/webhooks/paystack', PaystackWebhookController::class)->middleware('throttle:webhooks')->name('webhooks.paystack');

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'business'])->prefix('onboarding')->group(function () {
    Route::get('/', [OnboardingController::class, 'index'])->name('onboarding.index');
    Route::post('/branches', [OnboardingController::class, 'addBranch'])->name('onboarding.branches.add');
    Route::delete('/branches/{branch}', [OnboardingController::class, 'removeBranch'])->name('onboarding.branches.remove');
    Route::get('/{step}', [OnboardingController::class, 'show'])->whereNumber('step')->name('onboarding.show');
    Route::post('/{step}', [OnboardingController::class, 'store'])->whereNumber('step')->name('onboarding.store');
});

Route::middleware(['auth', 'business', 'onboarded'])->prefix('dashboard')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::post('/switch-branch', [DashboardController::class, 'switchBranch'])->name('branch.switch');
    Route::post('/switch-business/{business}', [DashboardController::class, 'switchBusiness'])->name('business.switch');

    Route::get('/branches', [BranchController::class, 'index'])->name('branches.index');
    Route::get('/branches/create', [BranchController::class, 'create'])->name('branches.create');
    Route::post('/branches', [BranchController::class, 'store'])->name('branches.store');
    Route::get('/branches/{branch}/edit', [BranchController::class, 'edit'])->name('branches.edit');
    Route::put('/branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
    Route::post('/branches/{branch}/toggle', [BranchController::class, 'toggle'])->name('branches.toggle');

    Route::get('/menu', [MenuController::class, 'index'])->name('menu.index');
    Route::post('/menu/categories', [MenuController::class, 'storeCategory'])->name('menu.categories.store');
    Route::put('/menu/categories/{category}', [MenuController::class, 'updateCategory'])->name('menu.categories.update');
    Route::delete('/menu/categories/{category}', [MenuController::class, 'destroyCategory'])->name('menu.categories.destroy');
    Route::get('/menu/items/create', [MenuController::class, 'createItem'])->name('menu.items.create');
    Route::post('/menu/items', [MenuController::class, 'storeItem'])->name('menu.items.store');
    Route::get('/menu/items/{item}/edit', [MenuController::class, 'editItem'])->name('menu.items.edit');
    Route::put('/menu/items/{item}', [MenuController::class, 'updateItem'])->name('menu.items.update');
    Route::delete('/menu/items/{item}', [MenuController::class, 'destroyItem'])->name('menu.items.destroy');

    Route::get('/branches/{branch}/menu', [BranchMenuController::class, 'edit'])->name('branch.menu.edit');
    Route::put('/branches/{branch}/menu', [BranchMenuController::class, 'update'])->name('branch.menu.update');
    Route::get('/branches/{branch}/qr', [QrController::class, 'show'])->name('branch.qr');
    Route::get('/branches/{branch}/qr.svg', [QrController::class, 'svg'])->name('branch.qr.svg');
    Route::post('/branches/{branch}/qr/rotate', [QrController::class, 'rotate'])->name('branch.qr.rotate');

    Route::post('/push-subscription', [PushSubscriptionController::class, 'store'])->middleware('throttle:20,1')->name('push.store');
    Route::delete('/push-subscription', [PushSubscriptionController::class, 'destroy'])->name('push.destroy');

    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('/billing/checkout', [BillingController::class, 'checkout'])->middleware('throttle:10,1')->name('billing.checkout');
    Route::get('/billing/callback', [BillingController::class, 'callback'])->name('billing.callback');

    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/status', [OrderController::class, 'transition'])->name('orders.transition');
});

Route::middleware(['auth', 'super-admin'])->prefix('admin')->group(function () {
    Route::get('/', AdminDashboardController::class)->name('admin.index');
    Route::get('/advertisements', [AdvertisementController::class, 'index'])->name('admin.advertisements');
    Route::get('/advertisements/create', [AdvertisementController::class, 'create'])->name('admin.advertisements.create');
    Route::post('/advertisements', [AdvertisementController::class, 'store'])->name('admin.advertisements.store');
    Route::get('/advertisements/{advertisement}/edit', [AdvertisementController::class, 'edit'])->name('admin.advertisements.edit');
    Route::put('/advertisements/{advertisement}', [AdvertisementController::class, 'update'])->name('admin.advertisements.update');
    Route::post('/advertisements/{advertisement}/toggle', [AdvertisementController::class, 'toggle'])->name('admin.advertisements.toggle');
    Route::delete('/advertisements/{advertisement}', [AdvertisementController::class, 'destroy'])->name('admin.advertisements.destroy');
    Route::get('/businesses', [DirectoryController::class, 'businesses'])->name('admin.businesses');
    Route::post('/businesses/{business}/toggle', [DirectoryController::class, 'toggleBusiness'])->name('admin.businesses.toggle');
    Route::get('/branches', [DirectoryController::class, 'branches'])->name('admin.branches');
    Route::get('/users', [DirectoryController::class, 'users'])->name('admin.users');
    Route::post('/users/{user}/toggle', [DirectoryController::class, 'toggleUser'])->name('admin.users.toggle');
    Route::get('/subscriptions', [DirectoryController::class, 'subscriptions'])->name('admin.subscriptions');
    Route::get('/audit-logs', [DirectoryController::class, 'audit'])->name('admin.audit');
    Route::get('/enquiries', [DirectoryController::class, 'enquiries'])->name('admin.enquiries');
    Route::post('/enquiries/{enquiry}/handled', [DirectoryController::class, 'handleEnquiry'])->name('admin.enquiries.handled');
    Route::get('/payments', [PaymentReviewController::class, 'index'])->name('admin.payments');
    Route::post('/payments/reviews/{review}', [PaymentReviewController::class, 'resolve'])->name('admin.payments.resolve');
    Route::post('/payments/{payment}/recheck', [PaymentReviewController::class, 'recheck'])->name('admin.payments.recheck');
});
