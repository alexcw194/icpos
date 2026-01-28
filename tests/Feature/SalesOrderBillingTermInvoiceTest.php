<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Models\SalesOrderBillingTerm;
use App\Models\TermOfPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SalesOrderBillingTermInvoiceTest extends TestCase
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
            'so_number' => 'SO-TEST-1',
            'order_date' => now()->toDateString(),
            'customer_po_number' => 'PO-TEST-1',
            'customer_po_date' => now()->toDateString(),
            'po_type' => 'goods',
            'discount_mode' => 'total',
            'tax_percent' => 0,
            'total' => 10000,
            'status' => 'open',
        ]);
    }

    public function test_admin_can_create_invoice_from_billing_term(): void
    {
        $admin = $this->makeAdminUser();
        $company = $this->makeCompany();
        $customer = $this->makeCustomer();

        TermOfPayment::firstOrCreate(
            ['code' => 'DP'],
            ['description' => 'Down Payment', 'is_active' => true]
        );

        $so = $this->makeSalesOrder($company, $customer);

        $term = SalesOrderBillingTerm::create([
            'sales_order_id' => $so->id,
            'seq' => 1,
            'top_code' => 'DP',
            'percent' => 50,
            'status' => 'planned',
        ]);

        $response = $this->actingAs($admin)->post(
            route('sales-orders.billing-terms.create-invoice', [$so, $term])
        );

        $response->assertRedirect();

        $term->refresh();
        $this->assertEquals('invoiced', $term->status);
        $this->assertNotNull($term->invoice_id);

        $invoice = Invoice::find($term->invoice_id);
        $this->assertNotNull($invoice);
        $this->assertEquals($so->id, $invoice->sales_order_id);
        $this->assertEquals($term->id, $invoice->so_billing_term_id);
        $this->assertEqualsWithDelta(5000.0, (float) $invoice->total, 0.01);

        $so->refresh();
        $this->assertEquals('partially_billed', $so->status);
    }

    public function test_non_admin_cannot_create_invoice_from_billing_term(): void
    {
        $user = User::factory()->create();
        $company = $this->makeCompany();
        $customer = $this->makeCustomer();

        TermOfPayment::firstOrCreate(
            ['code' => 'DP'],
            ['description' => 'Down Payment', 'is_active' => true]
        );

        $so = $this->makeSalesOrder($company, $customer);

        $term = SalesOrderBillingTerm::create([
            'sales_order_id' => $so->id,
            'seq' => 1,
            'top_code' => 'DP',
            'percent' => 50,
            'status' => 'planned',
        ]);

        $this->actingAs($user)
            ->post(route('sales-orders.billing-terms.create-invoice', [$so, $term]))
            ->assertStatus(403);
    }

    public function test_retention_requires_previous_terms_paid(): void
    {
        $admin = $this->makeAdminUser();
        $company = $this->makeCompany();
        $customer = $this->makeCustomer();

        TermOfPayment::firstOrCreate(
            ['code' => 'DP'],
            ['description' => 'Down Payment', 'is_active' => true]
        );
        TermOfPayment::firstOrCreate(
            ['code' => 'R1'],
            ['description' => 'Retention', 'is_active' => true]
        );

        $so = $this->makeSalesOrder($company, $customer);

        $dp = SalesOrderBillingTerm::create([
            'sales_order_id' => $so->id,
            'seq' => 1,
            'top_code' => 'DP',
            'percent' => 50,
            'status' => 'planned',
        ]);

        $r1 = SalesOrderBillingTerm::create([
            'sales_order_id' => $so->id,
            'seq' => 2,
            'top_code' => 'R1',
            'percent' => 50,
            'status' => 'planned',
        ]);

        $this->actingAs($admin)
            ->post(route('sales-orders.billing-terms.create-invoice', [$so, $r1]))
            ->assertStatus(422);

        $dp->update(['status' => 'paid']);

        $this->actingAs($admin)
            ->post(route('sales-orders.billing-terms.create-invoice', [$so, $r1]))
            ->assertRedirect();
    }
}
