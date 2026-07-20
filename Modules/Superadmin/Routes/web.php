<?php

// use App\Http\Controllers\BusinessController;
// use App\Http\Controllers\Modules;
// use Illuminate\Support\Facades\Route;

Route::get('/pricing', [Modules\Superadmin\Http\Controllers\PricingController::class, 'index'])->name('pricing');

Route::middleware('web', 'SetSessionData', 'auth', 'language', 'AdminSidebarMenu', 'superadmin')->prefix('superadmin')->group(function () {
    Route::get('/install', [Modules\Superadmin\Http\Controllers\InstallController::class, 'index']);
    Route::get('/install/update', [Modules\Superadmin\Http\Controllers\InstallController::class, 'update']);
    Route::get('/install/uninstall', [Modules\Superadmin\Http\Controllers\InstallController::class, 'uninstall']);

    Route::get('/', [Modules\Superadmin\Http\Controllers\SuperadminController::class, 'index']);
    Route::get('/stats', [Modules\Superadmin\Http\Controllers\SuperadminController::class, 'stats']);

    Route::get('/{business_id}/toggle-active/{is_active}', [Modules\Superadmin\Http\Controllers\BusinessController::class, 'toggleActive']);

    Route::get('/users/{business_id}', [Modules\Superadmin\Http\Controllers\BusinessController::class, 'usersList']);
    Route::post('/update-password', [Modules\Superadmin\Http\Controllers\BusinessController::class, 'updatePassword']);

    Route::resource('/business', Modules\Superadmin\Http\Controllers\BusinessController::class);
    Route::get('/business/{id}/destroy', [Modules\Superadmin\Http\Controllers\BusinessController::class, 'destroy']);

    Route::resource('/packages', 'Modules\Superadmin\Http\Controllers\PackagesController');
    Route::get('/packages/{id}/destroy', [Modules\Superadmin\Http\Controllers\PackagesController::class, 'destroy']);

    Route::get('/settings', [Modules\Superadmin\Http\Controllers\SuperadminSettingsController::class, 'edit']);
    Route::put('/settings', [Modules\Superadmin\Http\Controllers\SuperadminSettingsController::class, 'update']);
    Route::get('/edit-subscription/{id}', [Modules\Superadmin\Http\Controllers\SuperadminSubscriptionsController::class, 'editSubscription']);
    Route::post('/update-subscription', [Modules\Superadmin\Http\Controllers\SuperadminSubscriptionsController::class, 'updateSubscription']);
    Route::resource('/superadmin-subscription', 'Modules\Superadmin\Http\Controllers\SuperadminSubscriptionsController');

    Route::get('/communicator', [Modules\Superadmin\Http\Controllers\CommunicatorController::class, 'index']);
    Route::post('/communicator/send', [Modules\Superadmin\Http\Controllers\CommunicatorController::class, 'send']);
    Route::get('/communicator/get-history', [Modules\Superadmin\Http\Controllers\CommunicatorController::class, 'getHistory']);

    Route::resource('/frontend-pages', 'Modules\Superadmin\Http\Controllers\PageController');

    // Master Products (superadmin-defined catalog synced to all tenants)
    Route::get('/master-products', [Modules\Superadmin\Http\Controllers\SuperadminProductController::class, 'index'])->name('master-products.index');
    Route::get('/master-products/{id}', [Modules\Superadmin\Http\Controllers\SuperadminProductController::class, 'show'])->name('master-products.show');

    // Per-business supplier assignment management
    Route::get('/business/{business_id}/manage-suppliers', [Modules\Superadmin\Http\Controllers\BusinessController::class, 'manageSuppliers'])->name('business.manage-suppliers');
    Route::post('/business/{business_id}/sync-suppliers', [Modules\Superadmin\Http\Controllers\BusinessController::class, 'syncSuppliers'])->name('business.sync-suppliers');

    // Universal (chain-wide) customer management
    Route::get('/customers', [Modules\Superadmin\Http\Controllers\SuperadminCustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/{id}/toggle-global/{global}', [Modules\Superadmin\Http\Controllers\SuperadminCustomerController::class, 'toggleGlobal'])->name('customers.toggle-global');

    // Centralised invoice schemes/layouts + GST-wise shared series
    Route::get('/invoice-assignment', [Modules\Superadmin\Http\Controllers\InvoiceAssignmentController::class, 'index'])->name('invoice-assignment.index');
    Route::post('/invoice-assignment/assign', [Modules\Superadmin\Http\Controllers\InvoiceAssignmentController::class, 'assign'])->name('invoice-assignment.assign');
    Route::post('/invoice-assignment/business-gst', [Modules\Superadmin\Http\Controllers\InvoiceAssignmentController::class, 'saveBusinessGst'])->name('invoice-assignment.business-gst');
    Route::post('/invoice-assignment/scheme-gst', [Modules\Superadmin\Http\Controllers\InvoiceAssignmentController::class, 'saveSchemeGst'])->name('invoice-assignment.scheme-gst');
    Route::post('/invoice-assignment/reset-series', [Modules\Superadmin\Http\Controllers\InvoiceAssignmentController::class, 'resetSeries'])->name('invoice-assignment.reset-series');

});

Route::middleware('web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu')->group(function () {
    //Routes related to paypal checkout
    Route::get('/subscription/{package_id}/paypal-express-checkout', [Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'paypalExpressCheckout']);

    Route::get('/subscription/post-flutterwave-payment', [Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'postFlutterwavePaymentCallback']);

    Route::post('/subscription/pay-stack', [Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'getRedirectToPaystack']);
    Route::get('/subscription/post-payment-pay-stack-callback', [Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'postPaymentPaystackCallback']);

    //Routes related to pesapal checkout
    Route::get('/subscription/{package_id}/pesapal-callback', [Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'pesapalCallback'])->name('pesapalCallback');

    Route::get('/subscription/{package_id}/pay', [Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'pay']);
    Route::any('/subscription/{package_id}/confirm', [Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'confirm'])->name('subscription-confirm');
    Route::get('/all-subscriptions', [Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'allSubscriptions']);

    Route::get('/subscription/{package_id}/register-pay', [Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'registerPay'])->name('register-pay');

    Route::resource('/subscription', 'Modules\Superadmin\Http\Controllers\SubscriptionController');
});

Route::get('/page/{slug}', [Modules\Superadmin\Http\Controllers\PageController::class, 'showPage'])->name('frontend-pages');
