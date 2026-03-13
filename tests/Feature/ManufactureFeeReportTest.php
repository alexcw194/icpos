<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Item;
use App\Models\ManufactureCommissionNote;
use App\Models\ManufactureJob;
use App\Models\ManufactureRecipe;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\User;
use App\Services\ManufactureFeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ManufactureFeeReportTest extends TestCase
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

    public function test_non_admin_cannot_access_manufacture_fee_pages(): void
    {
        $user = $this->makeUserWithRole();

        $this->actingAs($user)
            ->get(route('manufacture-fees.index'))
            ->assertStatus(403);

        $this->actingAs($user)
            ->get(route('manufacture-commission-notes.index'))
            ->assertStatus(403);
    }

    public function test_manufacture_fee_report_and_commission_note_workflow(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $company = Company::create([
            'name' => 'Manufacture Fee Co',
            'alias' => 'MFC',
        ]);
        $customerA = Customer::create(['name' => 'Customer A']);
        $customerB = Customer::create(['name' => 'Customer B']);

        $aparItem = Item::create([
            'name' => 'APAR 6KG',
            'sku' => 'APAR-6KG',
            'price' => 500000,
            'family_code' => 'APAR',
        ]);
        $refillItem = Item::create([
            'name' => 'Refill APAR 6KG',
            'sku' => 'REFILL-6KG',
            'price' => 150000,
            'family_code' => 'REFILL',
        ]);
        $firehoseItem = Item::create([
            'name' => 'Firehose Canvas Bundle',
            'sku' => 'FH-BUNDLE',
            'price' => 700000,
            'family_code' => 'FIREHOSE',
            'item_type' => 'bundle',
        ]);
        $couplingComponent = Item::create([
            'name' => 'Coupling Instantaneous Aluminium',
            'sku' => 'COUPLING-AL',
            'price' => 50000,
        ]);
        $nonCouplingFirehose = Item::create([
            'name' => 'Firehose Tanpa Coupling',
            'sku' => 'FH-PLAIN',
            'price' => 400000,
            'family_code' => 'FIREHOSE',
        ]);

        ManufactureRecipe::create([
            'parent_item_id' => $firehoseItem->id,
            'component_item_id' => $couplingComponent->id,
            'qty_required' => 2,
        ]);

        $salesOrder = SalesOrder::create([
            'company_id' => $company->id,
            'customer_id' => $customerB->id,
            'so_number' => 'SO-MCF-001',
            'order_date' => now()->toDateString(),
            'customer_po_number' => 'PO-MCF-001',
            'customer_po_date' => now()->toDateString(),
            'discount_mode' => 'total',
            'lines_subtotal' => 500000,
            'total_discount_type' => 'amount',
            'total_discount_value' => 0,
            'total_discount_amount' => 0,
            'taxable_base' => 500000,
            'tax_percent' => 0,
            'tax_amount' => 0,
            'total' => 500000,
            'status' => 'open',
        ]);
        $soLineApar = SalesOrderLine::create([
            'sales_order_id' => $salesOrder->id,
            'position' => 1,
            'name' => $aparItem->name,
            'qty_ordered' => 1,
            'unit' => 'pcs',
            'unit_price' => 500000,
            'discount_type' => 'amount',
            'discount_value' => 0,
            'discount_amount' => 0,
            'line_subtotal' => 500000,
            'line_total' => 500000,
            'item_id' => $aparItem->id,
        ]);

        $invoiceA = Invoice::create([
            'company_id' => $company->id,
            'customer_id' => $customerA->id,
            'number' => 'INV-MCF-001',
            'date' => now()->toDateString(),
            'status' => 'posted',
            'subtotal' => 1150000,
            'discount' => 0,
            'tax_percent' => 0,
            'tax_amount' => 0,
            'total' => 1150000,
            'currency' => 'IDR',
            'posted_at' => now(),
        ]);
        InvoiceLine::create([
            'invoice_id' => $invoiceA->id,
            'item_id' => $refillItem->id,
            'description' => $refillItem->name,
            'unit' => 'pcs',
            'qty' => 2,
            'unit_price' => 150000,
            'discount_amount' => 0,
            'line_subtotal' => 300000,
            'line_total' => 300000,
        ]);
        InvoiceLine::create([
            'invoice_id' => $invoiceA->id,
            'item_id' => $firehoseItem->id,
            'description' => $firehoseItem->name,
            'unit' => 'pcs',
            'qty' => 1,
            'unit_price' => 700000,
            'discount_amount' => 0,
            'line_subtotal' => 700000,
            'line_total' => 700000,
        ]);
        InvoiceLine::create([
            'invoice_id' => $invoiceA->id,
            'item_id' => $nonCouplingFirehose->id,
            'description' => $nonCouplingFirehose->name,
            'unit' => 'pcs',
            'qty' => 1,
            'unit_price' => 150000,
            'discount_amount' => 0,
            'line_subtotal' => 150000,
            'line_total' => 150000,
        ]);

        $invoiceB = Invoice::create([
            'company_id' => $company->id,
            'customer_id' => $customerB->id,
            'sales_order_id' => $salesOrder->id,
            'number' => 'INV-MCF-002',
            'date' => now()->toDateString(),
            'status' => 'paid',
            'subtotal' => 500000,
            'discount' => 0,
            'tax_percent' => 0,
            'tax_amount' => 0,
            'total' => 500000,
            'currency' => 'IDR',
            'posted_at' => now(),
            'paid_at' => now(),
        ]);
        InvoiceLine::create([
            'invoice_id' => $invoiceB->id,
            'sales_order_id' => $salesOrder->id,
            'sales_order_line_id' => $soLineApar->id,
            'description' => $aparItem->name,
            'unit' => 'pcs',
            'qty' => 1,
            'unit_price' => 500000,
            'discount_amount' => 0,
            'line_subtotal' => 500000,
            'line_total' => 500000,
        ]);

        $draftInvoice = Invoice::create([
            'company_id' => $company->id,
            'customer_id' => $customerA->id,
            'number' => 'INV-MCF-DRAFT',
            'date' => now()->toDateString(),
            'status' => 'draft',
            'subtotal' => 999999,
            'discount' => 0,
            'tax_percent' => 0,
            'tax_amount' => 0,
            'total' => 999999,
            'currency' => 'IDR',
        ]);
        InvoiceLine::create([
            'invoice_id' => $draftInvoice->id,
            'item_id' => $aparItem->id,
            'description' => $aparItem->name,
            'unit' => 'pcs',
            'qty' => 9,
            'unit_price' => 999999,
            'discount_amount' => 0,
            'line_subtotal' => 999999,
            'line_total' => 999999,
        ]);

        ManufactureJob::create([
            'parent_item_id' => $aparItem->id,
            'qty_produced' => 1,
            'job_type' => 'fill',
            'json_components' => [],
            'produced_by' => $admin->id,
            'produced_at' => now(),
            'posted_at' => now(),
        ]);
        ManufactureJob::create([
            'parent_item_id' => $firehoseItem->id,
            'qty_produced' => 1,
            'job_type' => 'production',
            'json_components' => [],
            'produced_by' => $admin->id,
            'produced_at' => now(),
            'posted_at' => now(),
        ]);

        /** @var ManufactureFeeService $service */
        $service = app(ManufactureFeeService::class);
        $report = $service->buildReport([
            'month' => now()->format('Y-m'),
        ]);

        $this->assertEqualsWithDelta(1.0, (float) $report['summary']['apar_new_qty'], 0.01);
        $this->assertEqualsWithDelta(2.0, (float) $report['summary']['refill_tube_qty'], 0.01);
        $this->assertEqualsWithDelta(1.0, (float) $report['summary']['firehose_coupling_qty'], 0.01);
        $this->assertEqualsWithDelta(30000.0, (float) $report['summary']['apar_fee_total'], 0.01);
        $this->assertEqualsWithDelta(15000.0, (float) $report['summary']['firehose_fee_total'], 0.01);

        $rows = collect($report['categories'])->flatMap(fn ($category) => $category['rows'])->values();
        $this->assertTrue($rows->contains(fn ($row) => $row->category === 'apar_new' && $row->customer_name === 'Customer B'));
        $this->assertTrue($rows->contains(fn ($row) => $row->category === 'refill_tube' && $row->customer_name === 'Customer A'));
        $this->assertTrue($rows->contains(fn ($row) => $row->category === 'firehose_coupling' && $row->customer_name === 'Customer A'));
        $this->assertFalse($rows->contains(fn ($row) => $row->item_name === 'Firehose Tanpa Coupling'));
        $this->assertSame(1, $report['activity']['teams'][0]['job_count']);
        $this->assertSame(1, $report['activity']['teams'][1]['job_count']);

        $selectedSourceKeys = $rows->pluck('source_key')->take(2)->all();

        $this->actingAs($admin)
            ->post(route('manufacture-commission-notes.store'), [
                'month' => now()->format('Y-m'),
                'apar_fee_rate' => 10000,
                'firehose_fee_rate' => 15000,
                'note_date' => now()->toDateString(),
                'notes' => 'Batch komisi bulan ini',
                'source_keys' => $selectedSourceKeys,
            ])
            ->assertRedirect();

        $note = ManufactureCommissionNote::query()->firstOrFail();
        $this->assertSame('unpaid', $note->status);
        $this->assertCount(2, $note->lines);
        $this->assertDatabaseCount('manufacture_commission_note_lines', 2);

        $reportAfterCreate = $service->buildReport([
            'month' => now()->format('Y-m'),
        ]);
        $rowsAfterCreate = collect($reportAfterCreate['categories'])->flatMap(fn ($category) => $category['rows'])->keyBy('source_key');
        foreach ($selectedSourceKeys as $sourceKey) {
            $this->assertSame('in_unpaid_note', $rowsAfterCreate[$sourceKey]->source_status);
        }

        $this->actingAs($admin)
            ->from(route('manufacture-fees.index'))
            ->post(route('manufacture-commission-notes.store'), [
                'month' => now()->format('Y-m'),
                'apar_fee_rate' => 10000,
                'firehose_fee_rate' => 15000,
                'note_date' => now()->toDateString(),
                'source_keys' => $selectedSourceKeys,
            ])
            ->assertSessionHasErrors('source_keys');

        $this->actingAs($admin)
            ->patch(route('manufacture-commission-notes.mark-paid', $note), [
                'paid_at' => now()->toDateString(),
            ])
            ->assertRedirect(route('manufacture-commission-notes.show', $note));

        $note->refresh();
        $this->assertSame('paid', $note->status);
        $this->assertNotNull($note->paid_at);

        $reportAfterPaid = $service->buildReport([
            'month' => now()->format('Y-m'),
        ]);
        $rowsAfterPaid = collect($reportAfterPaid['categories'])->flatMap(fn ($category) => $category['rows'])->keyBy('source_key');
        foreach ($selectedSourceKeys as $sourceKey) {
            $this->assertSame('in_paid_note', $rowsAfterPaid[$sourceKey]->source_status);
        }

        $this->actingAs($admin)
            ->get(route('manufacture-commission-notes.index', [
                'month' => now()->format('Y-m'),
                'status' => 'paid',
            ]))
            ->assertOk()
            ->assertSee($note->number);

        $this->actingAs($admin)
            ->patch(route('manufacture-commission-notes.mark-unpaid', $note))
            ->assertRedirect(route('manufacture-commission-notes.show', $note));

        $note->refresh();
        $this->assertSame('unpaid', $note->status);
        $this->assertNull($note->paid_at);

        $this->actingAs($admin)
            ->delete(route('manufacture-commission-notes.destroy', $note))
            ->assertRedirect();

        $this->assertDatabaseMissing('manufacture_commission_notes', ['id' => $note->id]);
        $this->assertDatabaseCount('manufacture_commission_note_lines', 0);

        $this->actingAs($admin)
            ->get(route('manufacture-fees.index', ['month' => now()->format('Y-m')]))
            ->assertOk()
            ->assertSee('Manufacture Fee')
            ->assertSee('Create Commission Note');
    }
}
