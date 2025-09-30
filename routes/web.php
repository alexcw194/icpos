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
});

// =======================
// SuperAdmin-only: Global Settings
// =======================
Route::middleware(['auth', EnsureSuperAdmin::class])->group(function () {
    Route::get('/settings',  [SettingController::class, 'edit'])->name('settings.edit');
    Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');
});
