<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\{
    ProfileController,
    CustomerController,
    ItemController,
    ItemVariantController,
    QuotationController,
    InvoiceController,
    DeliveryController,
    CompanyController,
    AiSuggestController,
    UnitController,
    JenisController,
    BrandController,
    SalesOrderController,
    SalesOrderAttachmentController as SOAtt,
    WarehouseController,
    StockController,
    PurchaseOrderController, 
    GoodsReceiptController,
    ManufactureJobController,
    ManufactureRecipeController,
};
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Controllers\Auth\PasswordController;

// Root -> arahkan ke dashboard/login
Route::get('/', fn () => auth()->check()
    ? redirect()->route('dashboard')
    : redirect()->route('login')
);

require __DIR__.'/auth.php';

// =======================
// Authenticated area
// =======================
Route::middleware(['auth'])->group(function () {
    // Dashboard & Profile
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::get('/profile',  [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Change Password (user)
    Route::get('/password/change',  [PasswordController::class, 'edit'])->name('password.change');

    // Customers + Contacts + util
    Route::resource('customers', CustomerController::class);
    Route::post('customers/{customer}/contacts', [CustomerController::class, 'storeContact'])
        ->name('customers.contacts.store');
    Route::match(['put','patch'], 'customers/{customer}/contacts/{contact}',
        [CustomerController::class, 'updateContact'])->name('customers.contacts.update');
    Route::delete('customers/{customer}/contacts/{contact}',
        [CustomerController::class, 'destroyContact'])->name('customers.contacts.destroy');
    Route::patch('customers/{customer}/notes', [CustomerController::class,'updateNotes'])
        ->name('customers.notes');

    Route::post('/items/{item}/adjust', [StockController::class,'adjust'])
    ->name('stocks.adjust');

    // Quick-add & duplicate check
    Route::post('/customers/quick-store', [CustomerController::class, 'quickStore'])->name('customers.quick-store');
    Route::get ('/api/customers/dupcheck',  [CustomerController::class, 'dupCheck'])->name('customers.dupcheck');
    Route::get ('/api/customers/dup-check', [CustomerController::class, 'dupCheck'])->name('customers.dup-check'); // alias lama
    Route::get('/api/customers/search', [CustomerController::class, 'quickSearch'])
    ->name('customers.search');

    
    // Google Places proxy
    Route::get('/api/places/search', [AiSuggestController::class, 'company'])->name('places.search');

    // Items
    Route::resource('items', ItemController::class);
    Route::resource('items.variants', ItemVariantController::class)
        ->parameters(['variants' => 'variant'])
        ->shallow();
    Route::get('/api/items/search', [ItemController::class, 'quickSearch'])->name('items.search'); // <- tanpa ->middleware(['auth'])

    // Quotations
    Route::resource('quotations', QuotationController::class);
    Route::get ('quotations/{quotation}/print',         [QuotationController::class, 'print'])->name('quotations.print'); // tampilan cetak
    Route::get ('quotations/{quotation}/pdf',           [QuotationController::class, 'pdf'])->name('quotations.pdf');            // view inline
    Route::get ('quotations/{quotation}/pdf/download',  [QuotationController::class, 'pdfDownload'])->name('quotations.pdf-download'); // download
    Route::post('quotations/{quotation}/create-invoice',[InvoiceController::class, 'storeFromQuotation'])
        ->name('quotations.create-invoice');
    Route::post('quotations/{quotation}/sent',  [QuotationController::class, 'markSent'])->name('quotations.sent');
    Route::post('quotations/{quotation}/draft', [QuotationController::class, 'markDraft'])->name('quotations.draft');
    Route::post('quotations/{quotation}/po',    [QuotationController::class, 'markPo'])->name('quotations.po');
    Route::get ('quotations/{quotation}/preview',[QuotationController::class, 'preview'])->name('quotations.preview');
    Route::post('/quotations/{quotation}/email', [QuotationController::class, 'emailPdf'])
        ->name('quotations.email');

    // Invoices & Deliveries (read-only + actions)
    Route::resource('invoices',   InvoiceController::class)->only(['index','show','destroy']);
    Route::post('invoices/{invoice}/create-delivery', [DeliveryController::class, 'storeFromInvoice'])
        ->name('invoices.create-delivery');
    Route::resource('deliveries', DeliveryController::class);
    Route::post('deliveries/{delivery}/post', [DeliveryController::class, 'post'])->name('deliveries.post');
    Route::post('deliveries/{delivery}/cancel', [DeliveryController::class, 'cancel'])->name('deliveries.cancel');
    Route::get('deliveries/{delivery}/pdf', [DeliveryController::class, 'pdf'])->name('deliveries.pdf');
    Route::get('invoices/{invoice}/pdf-proforma', [\App\Http\Controllers\InvoiceController::class, 'pdfProforma'])
        ->name('invoices.pdf.proforma'); // allowed anytime
    Route::get('invoices/{invoice}/pdf', [\App\Http\Controllers\InvoiceController::class, 'pdfInvoice'])
        ->name('invoices.pdf'); // if draft => plain; if posted => watermark "Copy"
    Route::post('/invoices/{invoice}/post', [\App\Http\Controllers\InvoiceController::class, 'post'])
        ->name('invoices.post');
    Route::post('/invoices/{invoice}/update-receipt', [\App\Http\Controllers\InvoiceController::class, 'updateReceipt'])
        ->name('invoices.update-receipt');
    Route::get('/invoices/tt-pending', [\App\Http\Controllers\InvoiceController::class, 'ttPendingIndex'])
        ->name('invoices.tt-pending');
    Route::post('/invoices/{invoice}/mark-paid', [\App\Http\Controllers\InvoiceController::class, 'markPaid'])
        ->name('invoices.mark-paid');

    // =======================
    // Sales Orders
    // =======================
    Route::get('sales-orders', [SalesOrderController::class, 'index'])
        ->name('sales-orders.index');

    Route::get('/quotations/{quotation}/so/create', [SalesOrderController::class, 'createFromQuotation'])
        ->name('sales-orders.create-from-quotation');

    Route::post('/quotations/{quotation}/so', [SalesOrderController::class, 'storeFromQuotation'])
        ->name('sales-orders.store-from-quotation');

    Route::get('sales-orders/{salesOrder}', [SalesOrderController::class, 'show'])
        ->name('sales-orders.show');

    Route::get('deliveries/{delivery}/stock-check', [DeliveryController::class,'stockCheck'])
    ->name('deliveries.stock-check');

    Route::get('sales-orders/create', [SalesOrderController::class, 'create'])
        ->name('sales-orders.create')
        ->middleware(['auth', 'can:create,App\Models\SalesOrder']);

    Route::post('sales-orders', [SalesOrderController::class, 'store'])
        ->name('sales-orders.store')
        ->middleware(['auth', 'can:create,App\Models\SalesOrder']);
    // NEW: edit / update / cancel / destroy
    Route::get   ('sales-orders/{salesOrder}/edit', [SalesOrderController::class, 'edit'])
        ->name('sales-orders.edit');
    Route::match (['put','patch'], 'sales-orders/{salesOrder}', [SalesOrderController::class, 'update'])
        ->name('sales-orders.update');
    Route::post  ('sales-orders/{salesOrder}/cancel', [SalesOrderController::class, 'cancel'])
        ->name('sales-orders.cancel');
    Route::delete('sales-orders/{salesOrder}', [SalesOrderController::class, 'destroy'])
        ->name('sales-orders.destroy');

    // NEW: attachments
    Route::post  ('sales-orders/{salesOrder}/attachments', [SalesOrderController::class, 'storeAttachment'])
        ->name('sales-orders.attachments.store');
    Route::delete('sales-orders/{salesOrder}/attachments/{attachment}', [SalesOrderController::class, 'destroyAttachment'])
        ->name('sales-orders.attachments.destroy_legacy');
    Route::get('/invoices', [InvoiceController::class, 'index'])
        ->name('invoices.index');
        
    Route::get('/sales-orders/{salesOrder}/invoices/create', [InvoiceController::class,'createFromSo'])->name('invoices.create-from-so');
    Route::post('/sales-orders/{salesOrder}/invoices', [InvoiceController::class,'storeFromSo'])->name('invoices.store-from-so');

    Route::get('inventory/ledger', [\App\Http\Controllers\StockLedgerController::class, 'index'])
            ->name('inventory.ledger');
    Route::get('/inventory/summary', [\App\Http\Controllers\StockSummaryController::class, 'index'])
        ->middleware(['auth', \App\Http\Middleware\EnsureAdmin::class])
        ->name('inventory.summary');
    Route::get('/inventory/adjustments', [\App\Http\Controllers\StockAdjustmentController::class, 'index'])
        ->name('inventory.adjustments.index');
    Route::get('/inventory/adjustments/create', [\App\Http\Controllers\StockAdjustmentController::class, 'create'])
        ->name('inventory.adjustments.create');
    Route::post('/inventory/adjustments', [\App\Http\Controllers\StockAdjustmentController::class, 'store'])
        ->name('inventory.adjustments.store');
    Route::get('/inventory/reconciliation', [\App\Http\Controllers\StockReconciliationController::class, 'index'])
        ->name('inventory.reconciliation');

    Route::resource('manufacture-jobs', ManufactureJobController::class)->except(['edit', 'update', 'destroy']);

   Route::prefix('sales-orders/attachments')
    ->name('sales-orders.attachments.')
    ->group(function () {
        Route::get('/',        [SOAtt::class, 'index'])->name('index');      // ?draft_token=... | ?sales_order_id=...
        Route::post('/upload', [SOAtt::class, 'upload'])->name('upload');    // body: file + (draft_token | sales_order_id)
        Route::delete('/{attachment}', [SOAtt::class, 'destroy'])->name('destroy');
    });
    //Route::get('/sales-orders/attachments', [SalesOrderAttachmentController::class,'index'])
    //    ->name('sales-orders.attachments.index'); // list by ?draft_token=... atau ?sales_order_id=...
    //Route::post('/sales-orders/upload', [SalesOrderAttachmentController::class,'upload'])
    //    ->name('sales-orders.attachments.upload');
    //Route::delete('/sales-orders/attachments/{attachment}', [SalesOrderAttachmentController::class,'destroy'])
    //    ->name('sales-orders.attachments.destroy');

    // Cancel create → bersihkan semua file draft
    // routes/web.php (di dalam group auth)
    Route::delete('/sales-orders/create/cancel', function (Request $r) {
        $token = $r->input('draft_token') ?: session('so_draft_token');

        if ($token) {
            SOAtt::purgeDraft($token);   // <-- gunakan alias yang di-"use"
        }

        session()->forget('so_draft_token');
        return response()->noContent();  // 204
    })->name('sales-orders.create.cancel');

});

// =======================
// Admin-only area (EnsureAdmin)
// =======================
Route::middleware(['auth', EnsureAdmin::class])->group(function () {
    // Companies
    Route::resource('companies', CompanyController::class)->only(['index','create','store','edit','update']);
    Route::post('companies/{company}/make-default', [CompanyController::class, 'makeDefault'])
        ->name('companies.make-default');
    Route::delete('/companies/{company}', [CompanyController::class,'destroy'])
        ->name('companies.destroy');

    // Manufacture Recipes
    Route::get('manufacture-recipes/{parentItem}/manage', [ManufactureRecipeController::class, 'manage'])
        ->name('manufacture-recipes.manage');

    Route::match(['put','patch'], 'manufacture-recipes/{parentItem}', [ManufactureRecipeController::class, 'bulkUpdate'])
        ->name('manufacture-recipes.bulk-update');

    Route::resource('manufacture-recipes', ManufactureRecipeController::class)
        ->only(['index', 'create', 'store', 'destroy']);

    // Users (Add & Edit; delete kita tunda)
    Route::resource('users', AdminUserController::class)->except(['show','destroy']);

    // Permissions (read-only UI)
    Route::get('permissions', [RolePermissionController::class, 'index'])->name('permissions.index');

    // Master Data (admin)
    Route::resource('units',  UnitController::class)->except(['show']); // admin-only
    Route::resource('jenis',  JenisController::class)
        ->parameters(['jenis' => 'jeni']) // sesuaikan dgn controller edit(Jenis $jeni)
        ->except(['show']);
    Route::resource('brands', BrandController::class)->except(['show']);

    Route::resource('sizes',  \App\Http\Controllers\SizeController::class)->except(['show']);
    Route::resource('colors', \App\Http\Controllers\ColorController::class)->except(['show']);

    Route::resource('warehouses', WarehouseController::class)->except(['show']);
    Route::resource('banks', \App\Http\Controllers\BankController::class)->except(['show']);

    // PO
    Route::get('/po',               [PurchaseOrderController::class, 'index'])->name('po.index');
    Route::get('/po/create',        [PurchaseOrderController::class, 'create'])->name('po.create');
    Route::post('/po',              [PurchaseOrderController::class, 'store'])->name('po.store');
    Route::get('/po/{po}',          [PurchaseOrderController::class, 'show'])->name('po.show');
    Route::post('/po/{po}/approve', [PurchaseOrderController::class, 'approve'])->name('po.approve');

    // Receive from PO → build GR draft
    Route::get('/po/{po}/receive',      [PurchaseOrderController::class, 'receive'])->name('po.receive');
    Route::post('/po/{po}/receive',     [PurchaseOrderController::class, 'receiveStore'])->name('po.receive.store');

    // GR
    Route::get('/gr/{gr}',          [GoodsReceiptController::class, 'show'])->name('gr.show');
    Route::post('/gr/{gr}/post',    [GoodsReceiptController::class, 'post'])->name('gr.post');
});

// =======================
// SuperAdmin-only: Global Settings
// =======================
Route::middleware(['auth', EnsureSuperAdmin::class])->group(function () {
    Route::get('/settings',  [SettingController::class, 'edit'])->name('settings.edit');
    Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');
});
