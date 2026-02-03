<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\{
    DashboardController,
    ProfileController,
    CustomerController,
    DocumentController,
    ItemController,
    ItemVariantController,
    InventoryRowController,
    ProjectController,
    ProjectQuotationController,
    QuotationController,
    InvoiceController,
    BillingDocumentController,
    DeliveryController,
    LaborRateController,
    ProjectLaborController,
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
    BqLineCatalogController,
    SubContractorController,
    TermOfPaymentController,
    SupplierController,
};
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\ContactTitleController;
use App\Http\Controllers\Admin\ContactPositionController;
use App\Http\Controllers\Admin\DocumentCounterController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Controllers\Auth\PasswordController;
use Illuminate\Support\Facades\Auth;

// Root -> arahkan ke dashboard/login
Route::get('/', fn () => Auth::check()
    ? redirect()->route('dashboard')
    : redirect()->route('login')
);

require __DIR__.'/auth.php';

// =======================
// Authenticated area
// =======================
Route::middleware(['auth'])->group(function () {
    // Dashboard & Profile
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
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
    Route::get('/api/customers/{customer}/contacts', [CustomerController::class, 'contacts'])
    ->name('customers.contacts');

    
    // Google Places proxy
    Route::get('/api/places/search', [AiSuggestController::class, 'company'])->name('places.search');
    Route::get('/api/labor-rates', [LaborRateController::class, 'show'])->name('labor-rates.show');
    Route::post('/api/labor-rates', [LaborRateController::class, 'update'])->name('labor-rates.update');

    // =======================
// Items (READ-ONLY untuk semua user login)
// =======================
// NOTE: jangan pakai resource only(index,show) karena /items/{item} akan “nangkep” /items/create
Route::get('items', [ItemController::class, 'index'])->name('items.index');

// penting: batasi parameter {item} supaya /items/create tidak masuk ke show
Route::get('items/{item}', [ItemController::class, 'show'])
    ->whereNumber('item')
    ->name('items.show');

// Project Items (READ-ONLY untuk semua user login)
Route::get('project-items', [ItemController::class, 'index'])->name('project-items.index');
Route::get('project-items/{item}', [ItemController::class, 'show'])
    ->whereNumber('item')
    ->name('project-items.show');

    // Quick search items (tetap auth)
    Route::get('/api/items/search', [ItemController::class, 'quickSearch'])->name('items.search'); // <- tanpa ->middleware(['auth'])
    Route::get('/api/inventory/rows/search', [InventoryRowController::class, 'search'])
        ->name('inventory.rows.search');
    Route::get('/api/bq-line-catalogs/search', [BqLineCatalogController::class, 'search'])
        ->name('bq-line-catalogs.search');

    // Projects & Project Quotations (BQ)
    Route::resource('projects', ProjectController::class);
    Route::get('projects/labor', [ProjectLaborController::class, 'index'])->name('projects.labor.index');
    Route::post('projects/labor/default-sub-contractor', [ProjectLaborController::class, 'setDefaultSubContractor'])
        ->name('projects.labor.default-sub-contractor');
    Route::post('projects/labor/{item}', [ProjectLaborController::class, 'update'])
        ->whereNumber('item')
        ->name('projects.labor.update');
    Route::resource('projects.quotations', ProjectQuotationController::class);
    Route::get('projects/{project}/quotations/{quotation}/pdf', [ProjectQuotationController::class, 'pdf'])
        ->name('projects.quotations.pdf');
    Route::get('projects/{project}/quotations/{quotation}/pdf/download', [ProjectQuotationController::class, 'pdfDownload'])
        ->name('projects.quotations.pdf-download');
    Route::post('projects/{project}/quotations/{quotation}/reprice-labor', [ProjectQuotationController::class, 'repriceLabor'])
        ->name('projects.quotations.reprice-labor');
    Route::post('projects/{project}/quotations/{quotation}/won', [ProjectQuotationController::class, 'markWon'])
        ->name('projects.quotations.won');
    Route::post('projects/{project}/quotations/{quotation}/lost', [ProjectQuotationController::class, 'markLost'])
        ->name('projects.quotations.lost');

    // Quotations
    Route::resource('quotations', QuotationController::class);
    Route::get ('quotations/{quotation}/print',         [QuotationController::class, 'print'])->name('quotations.print'); // tampilan cetak
    Route::get ('quotations/{quotation}/pdf',           [QuotationController::class, 'pdf'])->name('quotations.pdf');            // view inline
    Route::get ('quotations/{quotation}/pdf/download',  [QuotationController::class, 'pdfDownload'])->name('quotations.pdf-download'); // download
    Route::post('quotations/{quotation}/draft', [QuotationController::class, 'markDraft'])->name('quotations.draft');
    Route::post('quotations/{quotation}/po',    [QuotationController::class, 'markPo'])->name('quotations.po');
    Route::get ('quotations/{quotation}/preview',[QuotationController::class, 'preview'])->name('quotations.preview');
    Route::post('/quotations/{quotation}/email', [QuotationController::class, 'emailPdf'])
        ->name('quotations.email');

    // Invoices & Deliveries (read-only + actions)
    Route::resource('invoices',   InvoiceController::class)->only(['index','show','destroy']);
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

    // Billing Documents (single record PI/INV)
    Route::post('sales-orders/{salesOrder}/billings', [BillingDocumentController::class, 'storeFromSalesOrder'])
        ->name('billings.store-from-so');
    Route::get('billings/{billing}', [BillingDocumentController::class, 'show'])
        ->name('billings.show');
    Route::match(['put','patch'], 'billings/{billing}', [BillingDocumentController::class, 'update'])
        ->name('billings.update');
    Route::post('billings/{billing}/issue-proforma', [BillingDocumentController::class, 'issueProforma'])
        ->name('billings.issue-proforma');
    Route::post('billings/{billing}/issue-invoice', [BillingDocumentController::class, 'issueInvoice'])
        ->name('billings.issue-invoice');
    Route::post('billings/{billing}/void', [BillingDocumentController::class, 'void'])
        ->name('billings.void');
    Route::get('billings/{billing}/pdf-proforma', [BillingDocumentController::class, 'pdfProforma'])
        ->name('billings.pdf.proforma');
    Route::get('billings/{billing}/pdf-invoice', [BillingDocumentController::class, 'pdfInvoice'])
        ->name('billings.pdf.invoice');

    Route::get('/api/item-variants/search', [ItemVariantController::class, 'quickSearch'])
        ->name('item-variants.search');

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

    Route::get('sales-orders/{salesOrder}/variations/create', [\App\Http\Controllers\SalesOrderVariationController::class, 'create'])
        ->name('sales-orders.variations.create');
    Route::post('sales-orders/{salesOrder}/variations', [\App\Http\Controllers\SalesOrderVariationController::class, 'store'])
        ->name('sales-orders.variations.store');
    Route::post('sales-orders/{salesOrder}/variations/{variation}/approve', [\App\Http\Controllers\SalesOrderVariationController::class, 'approve'])
        ->whereNumber('variation')
        ->name('sales-orders.variations.approve');
    Route::post('sales-orders/{salesOrder}/variations/{variation}/apply', [\App\Http\Controllers\SalesOrderVariationController::class, 'apply'])
        ->whereNumber('variation')
        ->name('sales-orders.variations.apply');

    // NEW: attachments
    Route::post  ('sales-orders/{salesOrder}/attachments', [SalesOrderController::class, 'storeAttachment'])
        ->name('sales-orders.attachments.store');
    Route::delete('sales-orders/{salesOrder}/attachments/{attachment}', [SalesOrderController::class, 'destroyAttachment'])
        ->name('sales-orders.attachments.destroy_legacy');
    Route::get('/invoices', [InvoiceController::class, 'index'])
        ->name('invoices.index');
        
    Route::post('/sales-orders/{salesOrder}/billing-terms/{term}/create-invoice', [InvoiceController::class,'storeFromBillingTerm'])
        ->whereNumber('term')
        ->name('sales-orders.billing-terms.create-invoice');

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

    // =======================
    // Documents
    // =======================
    Route::get('documents/my', [DocumentController::class, 'my'])
        ->name('documents.my');
    Route::get('documents', [DocumentController::class, 'index'])
        ->name('documents.index');
    Route::get('documents/pending', [DocumentController::class, 'pending'])
        ->name('documents.pending');
    Route::get('documents/create', [DocumentController::class, 'create'])
        ->name('documents.create')
        ->middleware(['can:create,App\Models\Document']);
    Route::post('documents', [DocumentController::class, 'store'])
        ->name('documents.store')
        ->middleware(['can:create,App\Models\Document']);
    Route::get('documents/{document}', [DocumentController::class, 'show'])
        ->name('documents.show');
    Route::get('documents/{document}/edit', [DocumentController::class, 'edit'])
        ->name('documents.edit');
    Route::match(['put','patch'], 'documents/{document}', [DocumentController::class, 'update'])
        ->name('documents.update');
    Route::post('documents/{document}/submit', [DocumentController::class, 'submit'])
        ->name('documents.submit');
    Route::post('documents/{document}/approve', [DocumentController::class, 'approve'])
        ->name('documents.approve');
    Route::post('documents/{document}/revise', [DocumentController::class, 'revise'])
        ->name('documents.revise');
    Route::post('documents/{document}/reject', [DocumentController::class, 'reject'])
        ->name('documents.reject');
    Route::delete('documents/{document}', [DocumentController::class, 'destroy'])
        ->name('documents.destroy');
    Route::post('documents/images/upload', [\App\Http\Controllers\DocumentImageController::class, 'upload'])
        ->name('documents.images.upload');
    Route::get('documents/{document}/pdf', [DocumentController::class, 'pdf'])
        ->name('documents.pdf');
    Route::get('documents/{document}/pdf/download', [DocumentController::class, 'pdfDownload'])
        ->name('documents.pdf-download');

});

// =======================
// Admin-only area (EnsureAdmin)
// =======================
Route::middleware(['auth', EnsureAdmin::class])->group(function () {
    // =======================
    // Items (WRITE untuk Admin/SuperAdmin)
    // =======================
    Route::resource('items', ItemController::class)->except(['index','show']);
    Route::resource('project-items', ItemController::class)
        ->parameters(['project-items' => 'item'])
        ->except(['index','show']);
    Route::post('items/{item}/transfer-to-project', [ItemController::class, 'transferListType'])
        ->name('items.transfer-to-project');
    Route::post('project-items/{item}/transfer-to-retail', [ItemController::class, 'transferListType'])
        ->name('project-items.transfer-to-retail');

    Route::resource('items.variants', ItemVariantController::class)
        ->parameters(['variants' => 'variant'])
        ->shallow();

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
    Route::resource('bq-line-catalogs', BqLineCatalogController::class)->except(['show']);
    Route::resource('bq-system-notes', \App\Http\Controllers\BqSystemNoteController::class)
        ->parameters(['bq-system-notes' => 'bqSystemNote'])
        ->except(['show']);
    Route::resource('sub-contractors', SubContractorController::class)
        ->parameters(['sub-contractors' => 'subContractor'])
        ->except(['show']);
    Route::resource('term-of-payments', TermOfPaymentController::class)
        ->parameters(['term-of-payments' => 'termOfPayment'])
        ->except(['show']);
    Route::resource('suppliers', SupplierController::class)->except(['show']);

    // Document Counters (manual numbering)
    Route::get('document-counters', [DocumentCounterController::class, 'index'])
        ->name('document-counters.index');
    Route::patch('document-counters/{documentCounter}', [DocumentCounterController::class, 'update'])
        ->name('document-counters.update');

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

    Route::resource('contact-titles', ContactTitleController::class)->except(['show', 'destroy']);
    Route::resource('contact-positions', ContactPositionController::class)->except(['show', 'destroy']);
});
