<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Item;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\User;
use App\Services\IncomeReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class IncomeReportCommissionNetTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdminUser(): User
    {
        $role = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_sales_item_details_include_commission_allocation_and_net_profit(): void
    {
        $admin = $this->makeAdminUser();
        $company = Company::create([
            'name' => 'Income Report Co',
            'alias' => 'IR',
            'is_taxable' => false,
        ]);
        $customer = Customer::create([
            'name' => 'Income Report Customer',
        ]);
        $item = Item::create([
            'name' => 'Income Report Item',
            'sku' => 'IR-ITEM-001',
            'price' => 100,
            'default_cost' => 60,
        ]);

        $so = SalesOrder::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'sales_user_id' => $admin->id,
            'so_number' => 'SO-IR-001',
            'order_date' => now()->toDateString(),
            'customer_po_number' => 'PO-IR-001',
            'customer_po_date' => now()->toDateString(),
            'po_type' => 'goods',
            'discount_mode' => 'total',
            'tax_percent' => 0,
            'tax_amount' => 0,
            'taxable_base' => 1000,
            'total' => 1000,
            'status' => 'open',
            'fee_amount' => 100,
            'under_amount' => 50,
        ]);

        SalesOrderLine::create([
            'sales_order_id' => $so->id,
            'position' => 1,
            'name' => 'Income Line',
            'qty_ordered' => 10,
            'unit' => 'pcs',
            'unit_price' => 100,
            'discount_type' => 'amount',
            'discount_value' => 0,
            'discount_amount' => 0,
            'line_subtotal' => 1000,
            'line_total' => 1000,
            'item_id' => $item->id,
            'item_variant_id' => null,
        ]);

        /** @var IncomeReportService $service */
        $service = app(IncomeReportService::class);
        $rows = $service->salesItemDetails([
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'basis' => 'both',
        ], 100);

        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertEqualsWithDelta(1000.0, (float) $row->revenue, 0.01);
        $this->assertEqualsWithDelta(600.0, (float) $row->cost_total, 0.01);
        $this->assertEqualsWithDelta(400.0, (float) $row->gross_profit, 0.01);
        $this->assertEqualsWithDelta(150.0, (float) $row->commission_allocated, 0.01);
        $this->assertEqualsWithDelta(250.0, (float) $row->net_profit, 0.01);

        $this->actingAs($admin)
            ->get(route('reports.income.index', [
                'start_date' => now()->toDateString(),
                'end_date' => now()->toDateString(),
                'basis' => 'both',
            ]))
            ->assertOk()
            ->assertSee('SO Commission')
            ->assertSee('SO Net Profit')
            ->assertSee('Commission')
            ->assertSee('Net Profit');
    }
}
