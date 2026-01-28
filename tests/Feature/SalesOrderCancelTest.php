<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\SalesOrderBillingTerm;
use App\Models\TermOfPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SalesOrderCancelTest extends TestCase
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
            'so_number' => 'SO-TEST-CANCEL-1',
            'order_date' => now()->toDateString(),
            'customer_po_number' => 'PO-TEST-CANCEL-1',
            'customer_po_date' => now()->toDateString(),
            'po_type' => 'goods',
            'discount_mode' => 'total',
            'tax_percent' => 0,
            'total' => 10000,
            'contract_value' => 10000,
            'status' => 'open',
        ]);
    }

    public function test_cancel_marks_so_and_terms(): void
    {
        $admin = $this->makeAdminUser();
        $company = $this->makeCompany();
        $customer = $this->makeCustomer();
        $so = $this->makeSalesOrder($company, $customer);

        TermOfPayment::firstOrCreate(
            ['code' => 'FINISH'],
            ['description' => 'Finish', 'is_active' => true]
        );

        $term = SalesOrderBillingTerm::create([
            'sales_order_id' => $so->id,
            'seq' => 1,
            'top_code' => 'FINISH',
            'percent' => 100,
            'status' => 'planned',
        ]);

        $this->actingAs($admin)->post(route('sales-orders.cancel', $so), [
            'cancel_reason' => 'Cancel test',
        ])->assertRedirect(route('sales-orders.show', $so));

        $so->refresh();
        $this->assertEquals('cancelled', $so->status);
        $this->assertNotNull($so->cancelled_at);
        $this->assertNotNull($so->cancelled_by_user_id);
        $this->assertEquals('Cancel test', $so->cancel_reason);

        $term->refresh();
        $this->assertEquals('cancelled', $term->status);
    }

    public function test_cancel_blocked_when_all_terms_paid(): void
    {
        $admin = $this->makeAdminUser();
        $company = $this->makeCompany();
        $customer = $this->makeCustomer();
        $so = $this->makeSalesOrder($company, $customer);

        TermOfPayment::firstOrCreate(
            ['code' => 'FINISH'],
            ['description' => 'Finish', 'is_active' => true]
        );

        SalesOrderBillingTerm::create([
            'sales_order_id' => $so->id,
            'seq' => 1,
            'top_code' => 'FINISH',
            'percent' => 100,
            'status' => 'paid',
        ]);

        $this->actingAs($admin)->post(route('sales-orders.cancel', $so), [
            'cancel_reason' => 'Cancel test',
        ])->assertSessionHasErrors('cancel_reason');
    }

    public function test_cancel_blocked_when_fully_billed(): void
    {
        $admin = $this->makeAdminUser();
        $company = $this->makeCompany();
        $customer = $this->makeCustomer();
        $so = $this->makeSalesOrder($company, $customer);
        $so->update(['status' => 'fully_billed']);

        $this->actingAs($admin)->post(route('sales-orders.cancel', $so), [
            'cancel_reason' => 'Cancel test',
        ])->assertSessionHasErrors('cancel_reason');
    }
}
