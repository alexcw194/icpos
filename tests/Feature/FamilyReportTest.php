<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\User;
use App\Services\FamilyPerformanceReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FamilyReportTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithRole(?string $roleName = null): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create();
        if ($roleName) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $user->assignRole($role);
        }

        return $user;
    }

    public function test_family_report_requires_admin_access_and_legacy_route_redirects(): void
    {
        $user = $this->makeUserWithRole();
        $admin = $this->makeUserWithRole('Admin');

        $this->actingAs($user)
            ->get(route('reports.family'))
            ->assertStatus(403);

        $this->actingAs($admin)
            ->get(route('reports.apar'))
            ->assertRedirect(route('reports.family'));
    }

    public function test_family_report_aggregates_primary_and_legacy_revenue_and_cost(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        [$company, $customer] = $this->seedReportBaseData();

        $refillItem = Item::create([
            'name' => 'Refill APAR 6KG',
            'sku' => 'REFILL-6KG',
            'price' => 300,
            'family_code' => 'REFILL',
        ]);
        $aparItem = Item::create([
            'name' => 'APAR 3KG',
            'sku' => 'APAR-3KG',
            'price' => 400,
            'family_code' => 'APAR',
        ]);
        $hydrantItem = Item::create([
            'name' => 'Hydrant Valve',
            'sku' => 'HYD-VALVE',
            'price' => 60,
            'family_code' => 'HYDRANT',
        ]);
        $firehoseItem = Item::create([
            'name' => 'Firehose 30m',
            'sku' => 'FIREHOSE-30',
            'price' => 100,
            'family_code' => 'FIREHOSE',
        ]);

        $salesOrder1 = $this->createSalesOrder($company->id, $customer->id, 'SO-FAMILY-001', 1000);
        $refillLine = SalesOrderLine::create([
            'sales_order_id' => $salesOrder1->id,
            'position' => 1,
            'name' => $refillItem->name,
            'qty_ordered' => 2,
            'unit' => 'pcs',
            'unit_price' => 300,
            'discount_type' => 'amount',
            'discount_value' => 0,
            'discount_amount' => 0,
            'line_subtotal' => 600,
            'line_total' => 600,
            'item_id' => $refillItem->id,
        ]);
        $aparLine = SalesOrderLine::create([
            'sales_order_id' => $salesOrder1->id,
            'position' => 2,
            'name' => $aparItem->name,
            'qty_ordered' => 1,
            'unit' => 'pcs',
            'unit_price' => 400,
            'discount_type' => 'amount',
            'discount_value' => 0,
            'discount_amount' => 0,
            'line_subtotal' => 400,
            'line_total' => 400,
            'item_id' => $aparItem->id,
        ]);

        $invoice1 = Invoice::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'sales_order_id' => $salesOrder1->id,
            'number' => 'INV-FAMILY-001',
            'date' => now()->toDateString(),
            'status' => 'posted',
            'subtotal' => 1000,
            'discount' => 0,
            'tax_percent' => 0,
            'tax_amount' => 0,
            'total' => 1000,
            'currency' => 'IDR',
            'posted_at' => now(),
        ]);

        InvoiceLine::create([
            'invoice_id' => $invoice1->id,
            'sales_order_id' => $salesOrder1->id,
            'sales_order_line_id' => $refillLine->id,
            'item_id' => $refillItem->id,
            'description' => $refillItem->name,
            'unit' => 'pcs',
            'qty' => 2,
            'unit_price' => 300,
            'discount_amount' => 0,
            'line_subtotal' => 600,
            'line_total' => 600,
        ]);
        InvoiceLine::create([
            'invoice_id' => $invoice1->id,
            'sales_order_id' => $salesOrder1->id,
            'sales_order_line_id' => $aparLine->id,
            'description' => $aparItem->name,
            'unit' => 'pcs',
            'qty' => 1,
            'unit_price' => 400,
            'discount_amount' => 0,
            'line_subtotal' => 400,
            'line_total' => 400,
        ]);

        $salesOrder2 = $this->createSalesOrder($company->id, $customer->id, 'SO-FAMILY-002', 500);
        SalesOrderLine::create([
            'sales_order_id' => $salesOrder2->id,
            'position' => 1,
            'name' => $hydrantItem->name,
            'qty_ordered' => 5,
            'unit' => 'pcs',
            'unit_price' => 60,
            'discount_type' => 'amount',
            'discount_value' => 0,
            'discount_amount' => 0,
            'line_subtotal' => 300,
            'line_total' => 300,
            'item_id' => $hydrantItem->id,
        ]);
        SalesOrderLine::create([
            'sales_order_id' => $salesOrder2->id,
            'position' => 2,
            'name' => $firehoseItem->name,
            'qty_ordered' => 2,
            'unit' => 'pcs',
            'unit_price' => 100,
            'discount_type' => 'amount',
            'discount_value' => 0,
            'discount_amount' => 0,
            'line_subtotal' => 200,
            'line_total' => 200,
            'item_id' => $firehoseItem->id,
        ]);

        $invoice2 = Invoice::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'sales_order_id' => $salesOrder2->id,
            'number' => 'INV-FAMILY-002',
            'date' => now()->toDateString(),
            'status' => 'paid',
            'subtotal' => 500,
            'discount' => 0,
            'tax_percent' => 0,
            'tax_amount' => 0,
            'total' => 500,
            'currency' => 'IDR',
            'posted_at' => now(),
            'paid_at' => now(),
        ]);

        InvoiceLine::create([
            'invoice_id' => $invoice2->id,
            'sales_order_id' => $salesOrder2->id,
            'description' => 'Down Payment',
            'unit' => 'ls',
            'qty' => 1,
            'unit_price' => 500,
            'discount_amount' => 0,
            'line_subtotal' => 500,
            'line_total' => 500,
        ]);

        $draftInvoice = Invoice::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'sales_order_id' => $salesOrder2->id,
            'number' => 'INV-FAMILY-DRAFT',
            'date' => now()->toDateString(),
            'status' => 'draft',
            'subtotal' => 999,
            'discount' => 0,
            'tax_percent' => 0,
            'tax_amount' => 0,
            'total' => 999,
            'currency' => 'IDR',
        ]);

        InvoiceLine::create([
            'invoice_id' => $draftInvoice->id,
            'sales_order_id' => $salesOrder2->id,
            'description' => 'Draft Header',
            'unit' => 'ls',
            'qty' => 1,
            'unit_price' => 999,
            'discount_amount' => 0,
            'line_subtotal' => 999,
            'line_total' => 999,
        ]);

        $poRefill = PurchaseOrder::create([
            'company_id' => $company->id,
            'supplier_id' => $customer->id,
            'number' => 'PO-FAMILY-001',
            'order_date' => now()->toDateString(),
            'status' => 'closed',
            'subtotal' => 250,
            'discount_amount' => 0,
            'tax_percent' => 0,
            'tax_amount' => 0,
            'total' => 250,
        ]);
        PurchaseOrderLine::create([
            'purchase_order_id' => $poRefill->id,
            'item_id' => $refillItem->id,
            'item_name_snapshot' => $refillItem->name,
            'qty_ordered' => 10,
            'qty_received' => 10,
            'uom' => 'pcs',
            'unit_price' => 25,
            'line_total' => 250,
        ]);
        $grRefill = GoodsReceipt::create([
            'company_id' => $company->id,
            'purchase_order_id' => $poRefill->id,
            'number' => 'GR-FAMILY-001',
            'gr_date' => now()->toDateString(),
            'status' => 'posted',
            'posted_at' => now(),
        ]);
        GoodsReceiptLine::create([
            'goods_receipt_id' => $grRefill->id,
            'item_id' => $refillItem->id,
            'item_name_snapshot' => $refillItem->name,
            'qty_received' => 10,
            'uom' => 'pcs',
            'unit_cost' => 25,
            'line_total' => 250,
        ]);

        $poApar = PurchaseOrder::create([
            'company_id' => $company->id,
            'supplier_id' => $customer->id,
            'number' => 'PO-FAMILY-002',
            'order_date' => now()->toDateString(),
            'status' => 'approved',
            'subtotal' => 200,
            'discount_amount' => 0,
            'tax_percent' => 0,
            'tax_amount' => 0,
            'total' => 200,
            'approved_at' => now(),
        ]);
        PurchaseOrderLine::create([
            'purchase_order_id' => $poApar->id,
            'item_id' => $aparItem->id,
            'item_name_snapshot' => $aparItem->name,
            'qty_ordered' => 4,
            'qty_received' => 0,
            'uom' => 'pcs',
            'unit_price' => 50,
            'line_total' => 200,
        ]);

        $poHydrant = PurchaseOrder::create([
            'company_id' => $company->id,
            'supplier_id' => $customer->id,
            'number' => 'PO-FAMILY-003',
            'order_date' => now()->toDateString(),
            'status' => 'fully_received',
            'subtotal' => 999,
            'discount_amount' => 0,
            'tax_percent' => 0,
            'tax_amount' => 0,
            'total' => 999,
            'approved_at' => now(),
        ]);
        PurchaseOrderLine::create([
            'purchase_order_id' => $poHydrant->id,
            'item_id' => $hydrantItem->id,
            'item_name_snapshot' => $hydrantItem->name,
            'qty_ordered' => 99,
            'qty_received' => 99,
            'uom' => 'pcs',
            'unit_price' => 10.09,
            'line_total' => 999,
        ]);
        $grHydrant = GoodsReceipt::create([
            'company_id' => $company->id,
            'purchase_order_id' => $poHydrant->id,
            'number' => 'GR-FAMILY-002',
            'gr_date' => now()->toDateString(),
            'status' => 'posted',
            'posted_at' => now(),
        ]);
        GoodsReceiptLine::create([
            'goods_receipt_id' => $grHydrant->id,
            'item_id' => $hydrantItem->id,
            'item_name_snapshot' => $hydrantItem->name,
            'qty_received' => 3,
            'uom' => 'pcs',
            'unit_cost' => 50,
            'line_total' => 150,
        ]);

        /** @var FamilyPerformanceReportService $service */
        $service = app(FamilyPerformanceReportService::class);
        $report = $service->buildReport([
            'from' => now()->toDateString(),
            'to' => now()->toDateString(),
        ]);

        $summary = collect($report['summary_rows'])->keyBy('family_code');

        $this->assertEqualsCanonicalizing(['APAR', 'FIREHOSE', 'HYDRANT', 'REFILL'], $summary->keys()->all());

        $this->assertEqualsWithDelta(600.0, (float) $summary['REFILL']->total_revenue, 0.01);
        $this->assertEqualsWithDelta(2.0, (float) $summary['REFILL']->total_qty_sold, 0.01);
        $this->assertEqualsWithDelta(250.0, (float) $summary['REFILL']->total_cost, 0.01);
        $this->assertEqualsWithDelta(350.0, (float) $summary['REFILL']->margin, 0.01);

        $this->assertEqualsWithDelta(400.0, (float) $summary['APAR']->total_revenue, 0.01);
        $this->assertEqualsWithDelta(1.0, (float) $summary['APAR']->total_qty_sold, 0.01);
        $this->assertEqualsWithDelta(200.0, (float) $summary['APAR']->total_cost, 0.01);

        $this->assertEqualsWithDelta(300.0, (float) $summary['HYDRANT']->total_revenue, 0.01);
        $this->assertEqualsWithDelta(5.0, (float) $summary['HYDRANT']->total_qty_sold, 0.01);
        $this->assertEqualsWithDelta(150.0, (float) $summary['HYDRANT']->total_cost, 0.01);
        $this->assertTrue(abs((float) $summary['HYDRANT']->total_cost - 999.0) > 0.01);

        $this->assertEqualsWithDelta(200.0, (float) $summary['FIREHOSE']->total_revenue, 0.01);
        $this->assertEqualsWithDelta(2.0, (float) $summary['FIREHOSE']->total_qty_sold, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $summary['FIREHOSE']->total_cost, 0.01);

        $this->assertNotNull($report['refill']);
        $this->assertEqualsWithDelta(2.0, (float) $report['refill']['total_tubes'], 0.01);
        $this->assertEqualsWithDelta(12.0, (float) $report['refill']['estimated_powder_kg'], 0.01);
        $this->assertSame('Refill APAR 6KG', $report['refill']['rows']->first()->item_name);

        $this->actingAs($admin)
            ->get(route('reports.family', [
                'from' => now()->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Family Report')
            ->assertSee('REFILL')
            ->assertSee('Estimated powder');
    }

    public function test_family_report_can_filter_specific_family_code(): void
    {
        $company = Company::create([
            'name' => 'Filter Co',
            'alias' => 'FC',
        ]);
        $customer = Customer::create([
            'name' => 'Filter Customer',
        ]);
        $refillItem = Item::create([
            'name' => 'Refill APAR 3 kg',
            'sku' => 'REFILL-3KG',
            'price' => 150,
            'family_code' => 'REFILL',
        ]);
        $aparItem = Item::create([
            'name' => 'APAR Portable',
            'sku' => 'APAR-PORT',
            'price' => 250,
            'family_code' => 'APAR',
        ]);

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'number' => 'INV-FILTER-001',
            'date' => now()->toDateString(),
            'status' => 'posted',
            'subtotal' => 400,
            'discount' => 0,
            'tax_percent' => 0,
            'tax_amount' => 0,
            'total' => 400,
            'currency' => 'IDR',
            'posted_at' => now(),
        ]);

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'item_id' => $refillItem->id,
            'description' => $refillItem->name,
            'unit' => 'pcs',
            'qty' => 1,
            'unit_price' => 150,
            'discount_amount' => 0,
            'line_subtotal' => 150,
            'line_total' => 150,
        ]);
        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'item_id' => $aparItem->id,
            'description' => $aparItem->name,
            'unit' => 'pcs',
            'qty' => 1,
            'unit_price' => 250,
            'discount_amount' => 0,
            'line_subtotal' => 250,
            'line_total' => 250,
        ]);

        /** @var FamilyPerformanceReportService $service */
        $service = app(FamilyPerformanceReportService::class);
        $report = $service->buildReport([
            'from' => now()->toDateString(),
            'to' => now()->toDateString(),
            'family_code' => 'REFILL',
        ]);

        $summary = collect($report['summary_rows']);

        $this->assertCount(1, $summary);
        $this->assertSame('REFILL', $summary->first()->family_code);
        $this->assertNotNull($report['refill']);
        $this->assertEqualsWithDelta(3.0, (float) $report['refill']['estimated_powder_kg'], 0.01);
    }

    private function seedReportBaseData(): array
    {
        $company = Company::create([
            'name' => 'Family Report Co',
            'alias' => 'FRC',
            'is_taxable' => false,
        ]);
        $customer = Customer::create([
            'name' => 'Family Report Customer',
        ]);

        return [$company, $customer];
    }

    private function createSalesOrder(int $companyId, int $customerId, string $number, float $total): SalesOrder
    {
        return SalesOrder::create([
            'company_id' => $companyId,
            'customer_id' => $customerId,
            'so_number' => $number,
            'order_date' => now()->toDateString(),
            'customer_po_number' => $number . '-PO',
            'customer_po_date' => now()->toDateString(),
            'discount_mode' => 'total',
            'lines_subtotal' => $total,
            'total_discount_type' => 'amount',
            'total_discount_value' => 0,
            'total_discount_amount' => 0,
            'taxable_base' => $total,
            'tax_percent' => 0,
            'tax_amount' => 0,
            'total' => $total,
            'status' => 'open',
        ]);
    }
}
