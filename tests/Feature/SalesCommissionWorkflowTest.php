<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\Project;
use App\Models\SalesCommissionNote;
use App\Models\SalesCommissionRule;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\Setting;
use App\Models\User;
use App\Services\SalesCommissionFeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SalesCommissionWorkflowTest extends TestCase
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

    public function test_non_admin_cannot_access_sales_commission_pages(): void
    {
        $user = $this->makeUserWithRole('Sales');

        $this->actingAs($user)
            ->get(route('sales-commission-fees.index'))
            ->assertStatus(403);

        $this->actingAs($user)
            ->get(route('sales-commission-notes.index'))
            ->assertStatus(403);

        $this->actingAs($user)
            ->get(route('sales-commission-rules.index'))
            ->assertStatus(403);
    }

    public function test_sales_commission_report_note_workflow_and_so_sync(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $salesA = $this->makeUserWithRole('Sales');
        $salesB = $this->makeUserWithRole('Sales');

        $company = Company::create(['name' => 'Sales Commission Co', 'alias' => 'SCC']);
        $customerA = Customer::create(['name' => 'Customer A']);
        $customerB = Customer::create(['name' => 'Customer B']);
        $customerC = Customer::create(['name' => 'Customer C']);
        $customerD = Customer::create(['name' => 'Customer D']);

        $rosenbauer = Brand::create(['name' => 'Rosenbauer', 'slug' => Str::slug('Rosenbauer'), 'is_active' => true]);
        $generalBrand = Brand::create(['name' => 'General', 'slug' => Str::slug('General'), 'is_active' => true]);

        SalesCommissionRule::create([
            'scope_type' => 'family',
            'family_code' => 'REFILL',
            'rate_percent' => 7,
            'is_active' => true,
        ]);

        $rosenbauerItem = Item::create([
            'name' => 'Rosenbauer APAR Truck',
            'sku' => 'ROSEN-APAR',
            'price' => 600,
            'brand_id' => $rosenbauer->id,
            'family_code' => 'APAR',
        ]);
        $refillItem = Item::create([
            'name' => 'Refill APAR 6KG',
            'sku' => 'REFILL-6KG',
            'price' => 400,
            'brand_id' => $generalBrand->id,
            'family_code' => 'REFILL',
        ]);
        $hydrantItem = Item::create([
            'name' => 'Hydrant Pump Set',
            'sku' => 'HYDRANT-PUMP',
            'price' => 200,
            'family_code' => 'HYDRANT',
        ]);
        $maintenanceItem = Item::create([
            'name' => 'Maintenance Contract',
            'sku' => 'MAINT-001',
            'price' => 100,
            'family_code' => 'SERVICE',
        ]);
        $unassignedItem = Item::create([
            'name' => 'Unassigned Row Item',
            'sku' => 'UNASSIGNED-ITEM',
            'price' => 100,
            'family_code' => 'APAR',
        ]);

        $goodsSo = SalesOrder::create([
            'company_id' => $company->id,
            'customer_id' => $customerA->id,
            'sales_user_id' => $salesA->id,
            'so_number' => 'SO-SC-001',
            'order_date' => now()->toDateString(),
            'customer_po_number' => 'PO-SC-001',
            'customer_po_date' => now()->toDateString(),
            'po_type' => 'goods',
            'discount_mode' => 'total',
            'tax_percent' => 0,
            'tax_amount' => 0,
            'taxable_base' => 900,
            'total' => 900,
            'status' => 'open',
            'under_amount' => 90,
            'fee_amount' => 0,
        ]);
        $goodsLineA = SalesOrderLine::create([
            'sales_order_id' => $goodsSo->id,
            'position' => 1,
            'name' => $rosenbauerItem->name,
            'qty_ordered' => 1,
            'unit' => 'pcs',
            'unit_price' => 600,
            'discount_type' => 'amount',
            'discount_value' => 0,
            'discount_amount' => 0,
            'line_subtotal' => 600,
            'line_total' => 600,
            'item_id' => $rosenbauerItem->id,
        ]);
        $goodsLineB = SalesOrderLine::create([
            'sales_order_id' => $goodsSo->id,
            'position' => 2,
            'name' => $refillItem->name,
            'qty_ordered' => 1,
            'unit' => 'pcs',
            'unit_price' => 400,
            'discount_type' => 'amount',
            'discount_value' => 0,
            'discount_amount' => 0,
            'line_subtotal' => 400,
            'line_total' => 400,
            'item_id' => $refillItem->id,
        ]);

        $project = Project::create([
            'company_id' => $company->id,
            'customer_id' => $customerB->id,
            'code' => 'PRJ-HYDRANT',
            'name' => 'Hydrant Project',
            'systems_json' => ['fire_hydrant'],
            'status' => 'open',
            'sales_owner_user_id' => $salesA->id,
        ]);
        $projectSo = SalesOrder::create([
            'company_id' => $company->id,
            'customer_id' => $customerB->id,
            'sales_user_id' => $salesA->id,
            'so_number' => 'SO-SC-002',
            'order_date' => now()->toDateString(),
            'customer_po_number' => 'PO-SC-002',
            'customer_po_date' => now()->toDateString(),
            'po_type' => 'project',
            'project_id' => $project->id,
            'discount_mode' => 'total',
            'tax_percent' => 0,
            'tax_amount' => 0,
            'taxable_base' => 200,
            'total' => 200,
            'status' => 'open',
            'under_amount' => 20,
            'fee_amount' => 0,
        ]);
        $projectLine = SalesOrderLine::create([
            'sales_order_id' => $projectSo->id,
            'position' => 1,
            'name' => $hydrantItem->name,
            'qty_ordered' => 1,
            'unit' => 'pcs',
            'unit_price' => 200,
            'discount_type' => 'amount',
            'discount_value' => 0,
            'discount_amount' => 0,
            'line_subtotal' => 200,
            'line_total' => 200,
            'item_id' => $hydrantItem->id,
        ]);

        $maintenanceSo = SalesOrder::create([
            'company_id' => $company->id,
            'customer_id' => $customerC->id,
            'sales_user_id' => $salesB->id,
            'so_number' => 'SO-SC-003',
            'order_date' => now()->toDateString(),
            'customer_po_number' => 'PO-SC-003',
            'customer_po_date' => now()->toDateString(),
            'po_type' => 'maintenance',
            'discount_mode' => 'total',
            'tax_percent' => 0,
            'tax_amount' => 0,
            'taxable_base' => 100,
            'total' => 100,
            'status' => 'open',
            'under_amount' => 0,
            'fee_amount' => 0,
        ]);
        $maintenanceLine = SalesOrderLine::create([
            'sales_order_id' => $maintenanceSo->id,
            'position' => 1,
            'name' => $maintenanceItem->name,
            'qty_ordered' => 1,
            'unit' => 'job',
            'unit_price' => 100,
            'discount_type' => 'amount',
            'discount_value' => 0,
            'discount_amount' => 0,
            'line_subtotal' => 100,
            'line_total' => 100,
            'item_id' => $maintenanceItem->id,
        ]);

        $unassignedSo = SalesOrder::create([
            'company_id' => $company->id,
            'customer_id' => $customerD->id,
            'sales_user_id' => null,
            'so_number' => 'SO-SC-004',
            'order_date' => now()->toDateString(),
            'customer_po_number' => 'PO-SC-004',
            'customer_po_date' => now()->toDateString(),
            'po_type' => 'goods',
            'discount_mode' => 'total',
            'tax_percent' => 0,
            'tax_amount' => 0,
            'taxable_base' => 100,
            'total' => 100,
            'status' => 'open',
            'under_amount' => 0,
            'fee_amount' => 0,
        ]);
        $unassignedLine = SalesOrderLine::create([
            'sales_order_id' => $unassignedSo->id,
            'position' => 1,
            'name' => $unassignedItem->name,
            'qty_ordered' => 1,
            'unit' => 'pcs',
            'unit_price' => 100,
            'discount_type' => 'amount',
            'discount_value' => 0,
            'discount_amount' => 0,
            'line_subtotal' => 100,
            'line_total' => 100,
            'item_id' => $unassignedItem->id,
        ]);

        $goodsInvoice = Invoice::create([
            'company_id' => $company->id,
            'customer_id' => $customerA->id,
            'sales_order_id' => $goodsSo->id,
            'number' => 'INV-SC-001',
            'date' => now()->toDateString(),
            'status' => 'posted',
            'subtotal' => 1000,
            'discount' => 100,
            'tax_percent' => 0,
            'tax_amount' => 0,
            'total' => 900,
            'currency' => 'IDR',
            'posted_at' => now(),
        ]);
        $goodsInvoiceLineA = InvoiceLine::create([
            'invoice_id' => $goodsInvoice->id,
            'sales_order_id' => $goodsSo->id,
            'sales_order_line_id' => $goodsLineA->id,
            'item_id' => $rosenbauerItem->id,
            'description' => $rosenbauerItem->name,
            'unit' => 'pcs',
            'qty' => 1,
            'unit_price' => 600,
            'discount_amount' => 0,
            'line_subtotal' => 600,
            'line_total' => 600,
        ]);
        $goodsInvoiceLineB = InvoiceLine::create([
            'invoice_id' => $goodsInvoice->id,
            'sales_order_id' => $goodsSo->id,
            'sales_order_line_id' => $goodsLineB->id,
            'item_id' => $refillItem->id,
            'description' => $refillItem->name,
            'unit' => 'pcs',
            'qty' => 1,
            'unit_price' => 400,
            'discount_amount' => 0,
            'line_subtotal' => 400,
            'line_total' => 400,
        ]);

        $projectInvoice = Invoice::create([
            'company_id' => $company->id,
            'customer_id' => $customerB->id,
            'sales_order_id' => $projectSo->id,
            'number' => 'INV-SC-002',
            'date' => now()->toDateString(),
            'status' => 'paid',
            'subtotal' => 200,
            'discount' => 0,
            'tax_percent' => 0,
            'tax_amount' => 0,
            'total' => 200,
            'currency' => 'IDR',
            'posted_at' => now(),
            'paid_at' => now(),
        ]);
        $projectInvoiceLine = InvoiceLine::create([
            'invoice_id' => $projectInvoice->id,
            'sales_order_id' => $projectSo->id,
            'sales_order_line_id' => $projectLine->id,
            'item_id' => $hydrantItem->id,
            'description' => $hydrantItem->name,
            'unit' => 'pcs',
            'qty' => 1,
            'unit_price' => 200,
            'discount_amount' => 0,
            'line_subtotal' => 200,
            'line_total' => 200,
        ]);

        $maintenanceInvoice = Invoice::create([
            'company_id' => $company->id,
            'customer_id' => $customerC->id,
            'sales_order_id' => $maintenanceSo->id,
            'number' => 'INV-SC-003',
            'date' => now()->toDateString(),
            'status' => 'posted',
            'subtotal' => 100,
            'discount' => 0,
            'tax_percent' => 0,
            'tax_amount' => 0,
            'total' => 100,
            'currency' => 'IDR',
            'posted_at' => now(),
        ]);
        $maintenanceInvoiceLine = InvoiceLine::create([
            'invoice_id' => $maintenanceInvoice->id,
            'sales_order_id' => $maintenanceSo->id,
            'sales_order_line_id' => $maintenanceLine->id,
            'item_id' => $maintenanceItem->id,
            'description' => $maintenanceItem->name,
            'unit' => 'job',
            'qty' => 1,
            'unit_price' => 100,
            'discount_amount' => 0,
            'line_subtotal' => 100,
            'line_total' => 100,
        ]);

        $unassignedInvoice = Invoice::create([
            'company_id' => $company->id,
            'customer_id' => $customerD->id,
            'sales_order_id' => $unassignedSo->id,
            'number' => 'INV-SC-004',
            'date' => now()->toDateString(),
            'status' => 'posted',
            'subtotal' => 100,
            'discount' => 0,
            'tax_percent' => 0,
            'tax_amount' => 0,
            'total' => 100,
            'currency' => 'IDR',
            'posted_at' => now(),
        ]);
        InvoiceLine::create([
            'invoice_id' => $unassignedInvoice->id,
            'sales_order_id' => $unassignedSo->id,
            'sales_order_line_id' => $unassignedLine->id,
            'item_id' => $unassignedItem->id,
            'description' => $unassignedItem->name,
            'unit' => 'pcs',
            'qty' => 1,
            'unit_price' => 100,
            'discount_amount' => 0,
            'line_subtotal' => 100,
            'line_total' => 100,
        ]);

        /** @var SalesCommissionFeeService $service */
        $service = app(SalesCommissionFeeService::class);
        $report = $service->buildReport(['month' => now()->format('Y-m')]);

        $this->assertCount(4, $report['rows']);
        $this->assertSame(1, $report['summary']['unassigned_sales_count']);

        $goodsRow = $report['rows']->firstWhere('sales_order_id', $goodsSo->id);
        $this->assertNotNull($goodsRow);
        $this->assertSame('sales-order|'.$goodsSo->id, $goodsRow->source_key);
        $this->assertEqualsWithDelta(900.00, (float) $goodsRow->revenue, 0.01);
        $this->assertEqualsWithDelta(90.00, (float) $goodsRow->under_allocated, 0.01);
        $this->assertEqualsWithDelta(810.00, (float) $goodsRow->commissionable_base, 0.01);
        $this->assertEqualsWithDelta(37.26, (float) $goodsRow->fee_amount, 0.01);
        $this->assertCount(2, $goodsRow->detail_rows);

        $rosenDetail = collect($goodsRow->detail_rows)->firstWhere('item_name', $rosenbauerItem->name);
        $this->assertNotNull($rosenDetail);
        $this->assertEqualsWithDelta(3.00, (float) $rosenDetail->rate_percent, 0.01);
        $this->assertEqualsWithDelta(14.58, (float) $rosenDetail->fee_amount, 0.01);

        $refillDetail = collect($goodsRow->detail_rows)->firstWhere('item_name', $refillItem->name);
        $this->assertNotNull($refillDetail);
        $this->assertEqualsWithDelta(7.00, (float) $refillDetail->rate_percent, 0.01);
        $this->assertEqualsWithDelta(22.68, (float) $refillDetail->fee_amount, 0.01);

        $projectRow = $report['rows']->firstWhere('sales_order_id', $projectSo->id);
        $this->assertNotNull($projectRow);
        $this->assertSame('Fire Hydrant', $projectRow->project_scope_label);
        $this->assertEqualsWithDelta(2.70, (float) $projectRow->fee_amount, 0.01);

        $maintenanceRow = $report['rows']->firstWhere('sales_order_id', $maintenanceSo->id);
        $this->assertNotNull($maintenanceRow);
        $this->assertSame('Maintenance', $maintenanceRow->project_scope_label);
        $this->assertEqualsWithDelta(5.00, (float) $maintenanceRow->fee_amount, 0.01);

        $unassignedRow = $report['rows']->firstWhere('sales_order_id', $unassignedSo->id);
        $this->assertFalse($unassignedRow->selectable);

        $this->actingAs($admin)
            ->post(route('sales-commission-notes.store'), [
                'month' => now()->format('Y-m'),
                'note_date' => now()->toDateString(),
                'source_keys' => [$goodsRow->source_key, $maintenanceRow->source_key],
            ])
            ->assertSessionHasErrors('source_keys');

        $response = $this->actingAs($admin)
            ->post(route('sales-commission-notes.store'), [
                'month' => now()->format('Y-m'),
                'note_date' => now()->toDateString(),
                'source_keys' => [$goodsRow->source_key, $projectRow->source_key],
            ]);

        $note = SalesCommissionNote::query()->firstOrFail();

        $response->assertRedirect(route('sales-commission-notes.show', $note));
        $this->assertSame($salesA->id, $note->sales_user_id);
        $this->assertCount(3, $note->lines);

        $goodsSo->refresh();
        $projectSo->refresh();
        $maintenanceSo->refresh();
        $this->assertEqualsWithDelta(37.26, (float) $goodsSo->fee_amount, 0.01);
        $this->assertEqualsWithDelta(2.70, (float) $projectSo->fee_amount, 0.01);
        $this->assertEqualsWithDelta(0.00, (float) $maintenanceSo->fee_amount, 0.01);
        $this->assertNull($goodsSo->fee_paid_at);
        $this->assertNull($projectSo->fee_paid_at);

        $reportAfterNote = $service->buildReport(['month' => now()->format('Y-m')]);
        $this->assertSame('in_unpaid_note', $reportAfterNote['rows']->firstWhere('sales_order_id', $goodsSo->id)->source_status);

        $this->actingAs($admin)
            ->patch(route('sales-commission-notes.mark-paid', $note), [
                'paid_at' => '2026-03-13',
            ])
            ->assertRedirect(route('sales-commission-notes.show', $note));

        $goodsSo->refresh();
        $projectSo->refresh();
        $this->assertSame('2026-03-13', optional($goodsSo->fee_paid_at)->toDateString());
        $this->assertSame('2026-03-13', optional($projectSo->fee_paid_at)->toDateString());

        $this->actingAs($admin)
            ->patch(route('sales-commission-notes.mark-unpaid', $note))
            ->assertRedirect(route('sales-commission-notes.show', $note));

        $goodsSo->refresh();
        $this->assertNull($goodsSo->fee_paid_at);

        $this->actingAs($admin)
            ->delete(route('sales-commission-notes.destroy', $note))
            ->assertRedirect(route('sales-commission-notes.index', [
                'month' => now()->format('Y-m'),
                'status' => 'unpaid',
                'sales_user_id' => $salesA->id,
            ]));

        $goodsSo->refresh();
        $projectSo->refresh();
        $this->assertEqualsWithDelta(0.00, (float) $goodsSo->fee_amount, 0.01);
        $this->assertEqualsWithDelta(0.00, (float) $projectSo->fee_amount, 0.01);
    }

    public function test_freelance_goods_uses_net_formula_while_project_stays_percentage(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $sales = $this->makeUserWithRole('Sales');
        $sales->assignRole(Role::firstOrCreate(['name' => 'Freelance', 'guard_name' => 'web']));

        Setting::setMany([
            'sales.commission.freelance.icpos_discount_percent' => '35',
            'sales.commission.default_rate_percent' => '5',
            'sales.commission.project.fire_alarm_rate_percent' => '5',
            'sales.commission.project.fire_hydrant_rate_percent' => '1.5',
            'sales.commission.project.maintenance_rate_percent' => '5',
        ]);

        $company = Company::create(['name' => 'Freelance Co', 'alias' => 'FLC']);
        $customer = Customer::create(['name' => 'Freelance Customer']);
        $item = Item::create([
            'name' => 'Freelance Goods',
            'sku' => 'FR-GOODS',
            'price' => 100,
            'family_code' => 'APAR',
        ]);
        $hydrantItem = Item::create([
            'name' => 'Freelance Project Hydrant',
            'sku' => 'FR-HYDRANT',
            'price' => 200,
            'family_code' => 'HYDRANT',
        ]);

        $goodsSo = SalesOrder::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'sales_user_id' => $sales->id,
            'so_number' => 'SO-FR-001',
            'order_date' => now()->toDateString(),
            'customer_po_number' => 'PO-FR-001',
            'customer_po_date' => now()->toDateString(),
            'po_type' => 'goods',
            'discount_mode' => 'total',
            'tax_percent' => 0,
            'tax_amount' => 0,
            'taxable_base' => 120,
            'total' => 120,
            'status' => 'open',
            'under_amount' => 10,
            'fee_amount' => 0,
        ]);
        SalesOrderLine::create([
            'sales_order_id' => $goodsSo->id,
            'position' => 1,
            'name' => $item->name,
            'qty_ordered' => 1,
            'unit' => 'pcs',
            'unit_price' => 120,
            'discount_type' => 'amount',
            'discount_value' => 0,
            'discount_amount' => 0,
            'line_subtotal' => 120,
            'line_total' => 120,
            'item_id' => $item->id,
            'commission_basis_unit_price' => 0,
        ]);

        $project = Project::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'code' => 'PRJ-FR-001',
            'name' => 'Freelance Project',
            'systems_json' => ['fire_hydrant'],
            'status' => 'open',
            'sales_owner_user_id' => $sales->id,
        ]);

        $projectSo = SalesOrder::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'sales_user_id' => $sales->id,
            'so_number' => 'SO-FR-002',
            'order_date' => now()->toDateString(),
            'customer_po_number' => 'PO-FR-002',
            'customer_po_date' => now()->toDateString(),
            'po_type' => 'project',
            'project_id' => $project->id,
            'discount_mode' => 'total',
            'tax_percent' => 0,
            'tax_amount' => 0,
            'taxable_base' => 200,
            'total' => 200,
            'status' => 'open',
            'under_amount' => 20,
            'fee_amount' => 0,
        ]);
        SalesOrderLine::create([
            'sales_order_id' => $projectSo->id,
            'position' => 1,
            'name' => $hydrantItem->name,
            'qty_ordered' => 1,
            'unit' => 'pcs',
            'unit_price' => 200,
            'discount_type' => 'amount',
            'discount_value' => 0,
            'discount_amount' => 0,
            'line_subtotal' => 200,
            'line_total' => 200,
            'item_id' => $hydrantItem->id,
            'commission_basis_unit_price' => 200,
        ]);

        foreach ([$goodsSo, $projectSo] as $salesOrder) {
            Invoice::create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'sales_order_id' => $salesOrder->id,
                'number' => 'INV-'.$salesOrder->so_number,
                'date' => now()->toDateString(),
                'status' => 'posted',
                'subtotal' => (float) $salesOrder->taxable_base,
                'discount' => 0,
                'tax_percent' => 0,
                'tax_amount' => 0,
                'total' => (float) $salesOrder->total,
                'currency' => 'IDR',
                'posted_at' => now(),
            ]);
        }

        $this->actingAs($admin);

        $report = app(SalesCommissionFeeService::class)->buildReport(['month' => now()->format('Y-m')]);

        $goodsRow = $report['rows']->firstWhere('sales_order_id', $goodsSo->id);
        $this->assertNotNull($goodsRow);
        $this->assertTrue($goodsRow->salesperson_is_freelance);
        $this->assertEqualsWithDelta(45.00, (float) $goodsRow->fee_amount, 0.01);

        $goodsDetail = collect($goodsRow->detail_rows)->first();
        $this->assertSame('freelance_net', $goodsDetail->commission_mode);
        $this->assertEqualsWithDelta(100.00, (float) $goodsDetail->basis_unit_price_snapshot, 0.01);
        $this->assertEqualsWithDelta(65.00, (float) $goodsDetail->basis_net_amount, 0.01);
        $this->assertEqualsWithDelta(110.00, (float) $goodsDetail->actual_net_amount, 0.01);
        $this->assertEqualsWithDelta(45.00, (float) $goodsDetail->fee_amount, 0.01);

        $projectRow = $report['rows']->firstWhere('sales_order_id', $projectSo->id);
        $this->assertNotNull($projectRow);
        $this->assertEqualsWithDelta(2.70, (float) $projectRow->fee_amount, 0.01);

        $projectDetail = collect($projectRow->detail_rows)->first();
        $this->assertSame('percentage', $projectDetail->commission_mode);
        $this->assertEqualsWithDelta(1.50, (float) $projectDetail->rate_percent, 0.01);
    }

    public function test_freelance_goods_falls_back_to_variant_price_when_parent_price_is_zero(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $sales = $this->makeUserWithRole('Sales');
        $sales->assignRole(Role::firstOrCreate(['name' => 'Freelance', 'guard_name' => 'web']));

        Setting::setMany([
            'sales.commission.freelance.icpos_discount_percent' => '35',
            'sales.commission.default_rate_percent' => '5',
            'sales.commission.project.fire_alarm_rate_percent' => '5',
            'sales.commission.project.fire_hydrant_rate_percent' => '1.5',
            'sales.commission.project.maintenance_rate_percent' => '5',
        ]);

        $company = Company::create(['name' => 'Freelance Var Co', 'alias' => 'FVC']);
        $customer = Customer::create(['name' => 'Freelance Variant Customer']);
        $item = Item::create([
            'name' => 'Refill ABC Powder PYROS',
            'sku' => 'FR-VAR-PARENT',
            'price' => 0,
            'family_code' => 'REFILL',
            'variant_type' => 'size',
        ]);
        $variant = ItemVariant::create([
            'item_id' => $item->id,
            'sku' => 'FR-VAR-5KG',
            'price' => 400000,
            'stock' => 0,
            'attributes' => ['size' => '5 kg'],
            'is_active' => true,
        ]);

        $salesOrder = SalesOrder::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'sales_user_id' => $sales->id,
            'so_number' => 'SO-FR-VAR-001',
            'order_date' => now()->toDateString(),
            'customer_po_number' => 'PO-FR-VAR-001',
            'customer_po_date' => now()->toDateString(),
            'po_type' => 'goods',
            'discount_mode' => 'total',
            'tax_percent' => 0,
            'tax_amount' => 0,
            'taxable_base' => 574000,
            'total' => 574000,
            'status' => 'open',
            'under_amount' => 74000,
            'fee_amount' => 0,
        ]);
        SalesOrderLine::create([
            'sales_order_id' => $salesOrder->id,
            'position' => 1,
            'name' => 'Refill ABC Powder PYROS - 5kg',
            'qty_ordered' => 1,
            'unit' => 'pcs',
            'unit_price' => 574000,
            'discount_type' => 'amount',
            'discount_value' => 0,
            'discount_amount' => 0,
            'line_subtotal' => 574000,
            'line_total' => 574000,
            'item_id' => $item->id,
            'item_variant_id' => $variant->id,
            'commission_basis_unit_price' => 0,
        ]);

        Invoice::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'sales_order_id' => $salesOrder->id,
            'number' => 'INV-SO-FR-VAR-001',
            'date' => now()->toDateString(),
            'status' => 'posted',
            'subtotal' => 574000,
            'discount' => 0,
            'tax_percent' => 0,
            'tax_amount' => 0,
            'total' => 574000,
            'currency' => 'IDR',
            'posted_at' => now(),
        ]);

        $this->actingAs($admin);

        $report = app(SalesCommissionFeeService::class)->buildReport(['month' => now()->format('Y-m')]);
        $row = $report['rows']->firstWhere('sales_order_id', $salesOrder->id);

        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(500000.00, (float) $row->commissionable_base, 0.01);
        $this->assertEqualsWithDelta(240000.00, (float) $row->fee_amount, 0.01);

        $detail = collect($row->detail_rows)->first();
        $this->assertSame('freelance_net', $detail->commission_mode);
        $this->assertEqualsWithDelta(400000.00, (float) $detail->basis_unit_price_snapshot, 0.01);
        $this->assertEqualsWithDelta(260000.00, (float) $detail->basis_net_amount, 0.01);
        $this->assertEqualsWithDelta(500000.00, (float) $detail->actual_net_amount, 0.01);
        $this->assertEqualsWithDelta(240000.00, (float) $detail->fee_amount, 0.01);
    }
}
