<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BillSplitController;
use App\Http\Controllers\Admin\BusinessSettingController;
use App\Http\Controllers\Admin\CampaignController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CategoryMediaController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CustomerGroupController;
use App\Http\Controllers\Admin\CustomerLoyaltyController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DiscountController;
use App\Http\Controllers\Admin\InventoryCategoryController;
use App\Http\Controllers\Admin\InventoryItemController;
use App\Http\Controllers\Admin\InventoryUnitController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\InvoiceTemplateController;
use App\Http\Controllers\Admin\KitchenDisplayController;
use App\Http\Controllers\Admin\LoyaltyRewardController;
use App\Http\Controllers\Admin\LoyaltyRuleController;
use App\Http\Controllers\Admin\MenuQrController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\OrderPrintJobController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PosController;
use App\Http\Controllers\Admin\PrinterController;
use App\Http\Controllers\Admin\PrintRouteController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductMediaController;
use App\Http\Controllers\Admin\ProductOptionController;
use App\Http\Controllers\Admin\ProductOptionGroupController;
use App\Http\Controllers\Admin\PurchaseController;
use App\Http\Controllers\Admin\PurchaseReturnController;
use App\Http\Controllers\Admin\RefundController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ShiftController;
use App\Http\Controllers\Admin\StockAdjustmentController;
use App\Http\Controllers\Admin\StockCountController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\SystemSettingController;
use App\Http\Controllers\Admin\TableController;
use App\Http\Controllers\Admin\TableSectionController;
use App\Http\Controllers\Admin\TableSessionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WaiterCallController as AdminWaiterCallController;
use App\Http\Controllers\Admin\WasteRecordController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\InitiatePaymentController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\PaymentCallbackController;
use App\Http\Controllers\WaiterCallController;
use App\Http\Middleware\EnsureNotInstalled;
use Illuminate\Support\Facades\Route;

Route::get('/', [MenuController::class, 'index'])->name('menu.index');
Route::get('/t/{qrToken}', [MenuController::class, 'table'])->name('menu.table');
Route::get('/menu/{product:slug}', [MenuController::class, 'show'])->name('menu.show');
Route::get('/cart', [MenuController::class, 'cart'])->name('cart.index');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:checkout')->name('checkout.store');
Route::get('/orders/{order}', [CheckoutController::class, 'show'])->middleware('throttle:order-tracking')->name('orders.show');

Route::get('/orders/{order}/pay/{gateway}', [InitiatePaymentController::class, 'store'])
    ->middleware('throttle:checkout')
    ->name('payments.initiate');
Route::match(['get', 'post'], '/payments/callback/{gateway}', [PaymentCallbackController::class, 'handle'])
    ->name('payments.callback');

Route::post('/call-waiter', [WaiterCallController::class, 'store'])->middleware('throttle:waiter-call')->name('waiter-call.store');

Route::post('/locale/{locale}', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware(EnsureNotInstalled::class)->prefix('install')->name('install.')->group(function () {
    Route::get('/', [InstallController::class, 'requirements'])->name('requirements');
    Route::get('database', [InstallController::class, 'showDatabase'])->name('database');
    Route::post('database', [InstallController::class, 'storeDatabase'])->name('database.store');
    Route::get('migrate', [InstallController::class, 'showMigrate'])->name('migrate');
    Route::post('migrate', [InstallController::class, 'runMigrate'])->name('migrate.run');
    Route::get('owner', [InstallController::class, 'showOwner'])->name('owner');
    Route::post('owner', [InstallController::class, 'storeOwner'])->name('owner.store');
    Route::get('business', [InstallController::class, 'showBusiness'])->name('business');
    Route::post('business', [InstallController::class, 'storeBusiness'])->name('business.store');
    Route::get('complete', [InstallController::class, 'complete'])->name('complete');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');

        Route::resource('categories', CategoryController::class)->except('show');
        Route::post('categories/{category}/media', [CategoryMediaController::class, 'store'])->name('categories.media.store');
        Route::delete('categories/{category}/media/{media}', [CategoryMediaController::class, 'destroy'])->name('categories.media.destroy');
        Route::patch('categories/{category}/media/{media}/primary', [CategoryMediaController::class, 'primary'])->name('categories.media.primary');

        Route::resource('products', ProductController::class)->except('show');
        Route::post('products/{product}/media', [ProductMediaController::class, 'store'])->name('products.media.store');
        Route::delete('products/{product}/media/{media}', [ProductMediaController::class, 'destroy'])->name('products.media.destroy');
        Route::patch('products/{product}/media/{media}/primary', [ProductMediaController::class, 'primary'])->name('products.media.primary');

        Route::post('products/{product}/option-groups', [ProductOptionGroupController::class, 'store'])->name('products.option-groups.store');
        Route::put('products/{product}/option-groups/{optionGroup}', [ProductOptionGroupController::class, 'update'])->name('products.option-groups.update');
        Route::delete('products/{product}/option-groups/{optionGroup}', [ProductOptionGroupController::class, 'destroy'])->name('products.option-groups.destroy');

        Route::post('products/{product}/option-groups/{optionGroup}/options', [ProductOptionController::class, 'store'])->name('products.option-groups.options.store');
        Route::put('products/{product}/option-groups/{optionGroup}/options/{option}', [ProductOptionController::class, 'update'])->name('products.option-groups.options.update');
        Route::delete('products/{product}/option-groups/{optionGroup}/options/{option}', [ProductOptionController::class, 'destroy'])->name('products.option-groups.options.destroy');

        Route::put('products/{product}/recipe', [ProductController::class, 'updateRecipe'])->name('products.recipe.update');
        Route::put('products/{product}/option-groups/{optionGroup}/options/{option}/recipe', [ProductOptionController::class, 'updateRecipe'])->name('products.option-groups.options.recipe.update');

        Route::resource('customers', CustomerController::class)->except(['show', 'destroy']);
        Route::patch('customers/{customer}/toggle-status', [CustomerController::class, 'toggleStatus'])->name('customers.toggle-status');
        Route::patch('customers/{customer}/loyalty/adjust', [CustomerLoyaltyController::class, 'adjust'])->name('customers.loyalty.adjust');

        Route::resource('customer-groups', CustomerGroupController::class)->parameters(['customer-groups' => 'customerGroup'])->except('show');

        Route::resource('discounts', DiscountController::class)->except('show');

        Route::resource('campaigns', CampaignController::class)->except('show');

        Route::resource('loyalty-rules', LoyaltyRuleController::class)->parameters(['loyalty-rules' => 'loyaltyRule'])->except('show');

        Route::resource('loyalty-rewards', LoyaltyRewardController::class)->parameters(['loyalty-rewards' => 'loyaltyReward'])->except('show');

        Route::prefix('inventory')->name('inventory.')->group(function () {
            Route::resource('items', InventoryItemController::class)->except('show');
            Route::resource('units', InventoryUnitController::class)->except('show');
            Route::resource('categories', InventoryCategoryController::class)->except('show');
        });

        Route::resource('suppliers', SupplierController::class)->except('show');

        Route::resource('purchases', PurchaseController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('purchases/{purchase}/receive', [PurchaseController::class, 'receive'])->name('purchases.receive');
        Route::patch('purchases/{purchase}/payment-status', [PurchaseController::class, 'updatePaymentStatus'])->name('purchases.payment-status');

        Route::get('stock-adjustments', [StockAdjustmentController::class, 'index'])->name('stock-adjustments.index');
        Route::get('stock-adjustments/create', [StockAdjustmentController::class, 'create'])->name('stock-adjustments.create');
        Route::post('stock-adjustments', [StockAdjustmentController::class, 'store'])->name('stock-adjustments.store');

        Route::get('waste-records', [WasteRecordController::class, 'index'])->name('waste-records.index');
        Route::get('waste-records/create', [WasteRecordController::class, 'create'])->name('waste-records.create');
        Route::post('waste-records', [WasteRecordController::class, 'store'])->name('waste-records.store');

        Route::get('purchase-returns', [PurchaseReturnController::class, 'index'])->name('purchase-returns.index');
        Route::get('purchase-returns/create', [PurchaseReturnController::class, 'create'])->name('purchase-returns.create');
        Route::post('purchase-returns', [PurchaseReturnController::class, 'store'])->name('purchase-returns.store');

        Route::get('stock-counts', [StockCountController::class, 'index'])->name('stock-counts.index');
        Route::get('stock-counts/create', [StockCountController::class, 'create'])->name('stock-counts.create');
        Route::post('stock-counts', [StockCountController::class, 'store'])->name('stock-counts.store');
        Route::get('stock-counts/{count}', [StockCountController::class, 'show'])->name('stock-counts.show');
        Route::patch('stock-counts/{count}/submit', [StockCountController::class, 'submit'])->name('stock-counts.submit');
        Route::patch('stock-counts/{count}/complete', [StockCountController::class, 'complete'])->name('stock-counts.complete');

        Route::resource('table-sections', TableSectionController::class)->parameters(['table-sections' => 'tableSection'])->except('show');

        Route::resource('tables', TableController::class)->except('show');
        Route::get('tables/{table}/qr', [TableController::class, 'qr'])->name('tables.qr');
        Route::get('tables/{table}/qr/png', [TableController::class, 'qrPng'])->name('tables.qr.png');
        Route::get('tables/{table}/qr/svg', [TableController::class, 'qrSvg'])->name('tables.qr.svg');
        Route::post('tables/{table}/qr/regenerate', [TableController::class, 'regenerateQr'])->name('tables.qr.regenerate');

        Route::get('qr/menu', [MenuQrController::class, 'show'])->name('qr.menu');
        Route::get('qr/menu/png', [MenuQrController::class, 'png'])->name('qr.menu.png');
        Route::get('qr/menu/svg', [MenuQrController::class, 'svg'])->name('qr.menu.svg');

        Route::get('settings/business', [BusinessSettingController::class, 'edit'])->name('settings.business.edit');
        Route::put('settings/business', [BusinessSettingController::class, 'update'])->name('settings.business.update');
        Route::post('settings/business/logo', [BusinessSettingController::class, 'storeLogo'])->name('settings.business.logo.store');
        Route::delete('settings/business/logo', [BusinessSettingController::class, 'destroyLogo'])->name('settings.business.logo.destroy');

        Route::get('settings/system', [SystemSettingController::class, 'edit'])->name('settings.system.edit');
        Route::put('settings/system', [SystemSettingController::class, 'update'])->name('settings.system.update');

        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');

        Route::get('orders/{order}/bill-split/create', [BillSplitController::class, 'create'])->name('orders.bill-split.create');
        Route::post('orders/{order}/bill-split', [BillSplitController::class, 'store'])->name('orders.bill-split.store');
        Route::post('bill-split-parts/{part}/payments', [BillSplitController::class, 'storePayment'])->name('bill-split-parts.payments.store');

        Route::post('orders/{order}/invoice', [InvoiceController::class, 'generate'])->name('orders.invoice.generate');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::resource('invoice-templates', InvoiceTemplateController::class)->parameters(['invoice-templates' => 'invoiceTemplate'])->except('show');
        Route::patch('invoice-templates/{invoiceTemplate}/make-default', [InvoiceTemplateController::class, 'makeDefault'])->name('invoice-templates.make-default');

        Route::resource('printers', PrinterController::class)->except('show');
        Route::resource('print-routes', PrintRouteController::class)->parameters(['print-routes' => 'printRoute'])->except('show');
        Route::post('orders/{order}/print-receipt', [OrderPrintJobController::class, 'store'])->name('orders.print-receipt');

        Route::get('kitchen', [KitchenDisplayController::class, 'index'])->name('kitchen.index');

        Route::prefix('pos')->name('pos.')->group(function () {
            Route::get('/', [PosController::class, 'index'])->name('index');
            Route::get('counter', [PosController::class, 'counter'])->name('counter');
            Route::post('counter/orders', [PosController::class, 'storeCounter'])->name('counter.orders.store');
            Route::get('tables/{table}', [PosController::class, 'table'])->name('table');
            Route::post('tables/{table}/orders', [PosController::class, 'storeDineIn'])->name('table.orders.store');
            Route::post('orders/{order}/items', [PosController::class, 'addItems'])->name('orders.items.store');
        });

        Route::get('tables/{table}/session', [TableSessionController::class, 'show'])->name('tables.session');
        Route::post('tables/{table}/session/open', [TableSessionController::class, 'open'])->name('tables.session.open');
        Route::patch('table-sessions/{tableSession}/close', [TableSessionController::class, 'close'])->name('table-sessions.close');
        Route::patch('table-sessions/{tableSession}/transfer', [TableSessionController::class, 'transfer'])->name('table-sessions.transfer');
        Route::patch('table-sessions/{tableSession}/merge', [TableSessionController::class, 'merge'])->name('table-sessions.merge');

        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::post('orders/{order}/payments', [PaymentController::class, 'store'])->name('orders.payments.store');
        Route::patch('payments/{payment}/status', [PaymentController::class, 'updateStatus'])->name('payments.status');
        Route::post('payments/{payment}/refund', [RefundController::class, 'store'])->name('payments.refund');

        Route::get('shifts', [ShiftController::class, 'index'])->name('shifts.index');
        Route::post('shifts', [ShiftController::class, 'store'])->name('shifts.store');
        Route::get('shifts/{shift}', [ShiftController::class, 'show'])->name('shifts.show');
        Route::post('shifts/{shift}/cash-movements', [ShiftController::class, 'recordCashMovement'])->name('shifts.record-cash-movement');
        Route::patch('shifts/{shift}/close', [ShiftController::class, 'close'])->name('shifts.close');

        Route::patch('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
        Route::patch('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');

        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');

        Route::get('waiter-calls', [AdminWaiterCallController::class, 'index'])->name('waiter-calls.index');
        Route::patch('waiter-calls/{waiterCall}/resolve', [AdminWaiterCallController::class, 'resolve'])->name('waiter-calls.resolve');
    });
});
