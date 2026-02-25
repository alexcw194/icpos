<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SalesOrderCommissionTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeSalesOrder(string $status = 'open'): SalesOrder
    {
        $company = Company::create([
            'name' => 'SO Commission Co',
            'alias' => 'SOCOM',
        ]);

        $customer = Customer::create([
            'name' => 'SO Commission Customer',
        ]);

        return SalesOrder::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'so_number' => 'SO-COM-001',
            'order_date' => now()->toDateString(),
            'customer_po_number' => 'PO-COM-001',
            'customer_po_date' => now()->toDateString(),
            'po_type' => 'goods',
            'discount_mode' => 'total',
            'tax_percent' => 0,
            'tax_amount' => 0,
            'taxable_base' => 0,
            'total' => 0,
            'status' => $status,
            'fee_amount' => 0,
            'under_amount' => 0,
        ]);
    }

    public function test_admin_can_update_commission_and_payment_dates(): void
    {
        $admin = $this->makeUser('Admin');
        $so = $this->makeSalesOrder();

        $this->actingAs($admin)
            ->patch(route('sales-orders.commission.update', $so), [
                'fee_amount' => 100000,
                'under_amount' => 25000,
                'fee_paid_at' => '2026-03-01',
                'under_paid_at' => '2026-03-02',
            ])
            ->assertRedirect(route('sales-orders.show', $so));

        $this->assertDatabaseHas('sales_orders', [
            'id' => $so->id,
            'fee_amount' => 100000,
            'under_amount' => 25000,
            'fee_paid_at' => '2026-03-01',
            'under_paid_at' => '2026-03-02',
        ]);
    }

    public function test_superadmin_can_update_commission_even_when_so_not_open(): void
    {
        $superAdmin = $this->makeUser('SuperAdmin');
        $so = $this->makeSalesOrder('delivered');

        $this->actingAs($superAdmin)
            ->patch(route('sales-orders.commission.update', $so), [
                'fee_amount' => 50000,
                'under_amount' => 0,
                'fee_paid_at' => '2026-03-03',
                'under_paid_at' => '2026-03-03',
            ])
            ->assertRedirect(route('sales-orders.show', $so));

        $so->refresh();
        $this->assertSame('50000.00', (string) $so->fee_amount);
        $this->assertSame('0.00', (string) $so->under_amount);
        $this->assertSame('2026-03-03', optional($so->fee_paid_at)->toDateString());
        $this->assertNull($so->under_paid_at);
    }

    public function test_finance_user_cannot_update_commission(): void
    {
        $finance = $this->makeUser('Finance');
        $so = $this->makeSalesOrder();

        $this->actingAs($finance)
            ->patch(route('sales-orders.commission.update', $so), [
                'fee_amount' => 1000,
                'under_amount' => 1000,
            ])
            ->assertStatus(403);
    }

    public function test_zero_amount_will_clear_paid_dates(): void
    {
        $admin = $this->makeUser('Admin');
        $so = $this->makeSalesOrder();
        $so->update([
            'fee_amount' => 2000,
            'under_amount' => 1500,
            'fee_paid_at' => '2026-03-01',
            'under_paid_at' => '2026-03-01',
        ]);

        $this->actingAs($admin)
            ->patch(route('sales-orders.commission.update', $so), [
                'fee_amount' => 0,
                'under_amount' => 0,
                'fee_paid_at' => '2026-03-05',
                'under_paid_at' => '2026-03-05',
            ])
            ->assertRedirect(route('sales-orders.show', $so));

        $so->refresh();
        $this->assertSame('0.00', (string) $so->fee_amount);
        $this->assertSame('0.00', (string) $so->under_amount);
        $this->assertNull($so->fee_paid_at);
        $this->assertNull($so->under_paid_at);
    }
}
