<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\SalesOrderVariation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SalesOrderVariationTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdminUser(): User
    {
        $role = Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }

    private function makeCompany(): Company
    {
        return Company::create([
            'name' => 'Test Company',
            'alias' => 'TST',
        ]);
    }

    private function makeCustomer(): Customer
    {
        return Customer::create([
            'name' => 'Test Customer',
        ]);
    }

    private function makeSalesOrder(Company $company, Customer $customer): SalesOrder
    {
        return SalesOrder::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'sales_user_id' => null,
            'so_number' => 'SO-TEST-VO-1',
            'order_date' => now()->toDateString(),
            'customer_po_number' => 'PO-TEST-VO-1',
            'customer_po_date' => now()->toDateString(),
            'po_type' => 'goods',
            'discount_mode' => 'total',
            'tax_percent' => 0,
            'total' => 10000,
            'contract_value' => 10000,
            'status' => 'open',
        ]);
    }

    public function test_admin_can_create_approve_apply_vo(): void
    {
        $admin = $this->makeAdminUser();
        $company = $this->makeCompany();
        $customer = $this->makeCustomer();
        $so = $this->makeSalesOrder($company, $customer);

        $this->actingAs($admin)->post(route('sales-orders.variations.store', $so), [
            'vo_date' => now()->toDateString(),
            'delta_amount' => '1000',
            'reason' => 'Add work',
        ])->assertRedirect(route('sales-orders.show', $so));

        $vo = SalesOrderVariation::first();
        $this->assertNotNull($vo);
        $this->assertEquals('draft', $vo->status);

        $this->actingAs($admin)
            ->post(route('sales-orders.variations.approve', [$so, $vo]))
            ->assertRedirect();

        $vo->refresh();
        $this->assertEquals('approved', $vo->status);

        $this->actingAs($admin)
            ->post(route('sales-orders.variations.apply', [$so, $vo]))
            ->assertRedirect();

        $vo->refresh();
        $this->assertEquals('applied', $vo->status);

        $so->refresh();
        $this->assertEqualsWithDelta(11000.0, (float) $so->contract_value, 0.01);
    }
}
