<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Jenis;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerDuplicateGuardAndMergeTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdminUser(): User
    {
        $role = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }

    private function makeJenis(): Jenis
    {
        return Jenis::create([
            'name' => 'Hotel',
            'slug' => 'hotel',
            'is_active' => true,
        ]);
    }

    public function test_store_blocks_exact_duplicate_name(): void
    {
        $admin = $this->makeAdminUser();
        $jenis = $this->makeJenis();

        Customer::create([
            'name' => 'PT Glamp Nusa',
            'jenis_id' => $jenis->id,
            'sales_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('customers.store'), [
                'name' => 'Glamp Nusa',
                'jenis_id' => $jenis->id,
                'sales_user_id' => $admin->id,
            ])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Customer::count());
    }

    public function test_store_requires_decision_for_similar_name(): void
    {
        $admin = $this->makeAdminUser();
        $jenis = $this->makeJenis();

        Customer::create([
            'name' => 'Glamp Nusa',
            'jenis_id' => $jenis->id,
            'sales_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('customers.store'), [
                'name' => 'Glamp Nusaa',
                'jenis_id' => $jenis->id,
                'sales_user_id' => $admin->id,
            ])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Customer::count());

        $this->actingAs($admin)
            ->post(route('customers.store'), [
                'name' => 'Glamp Nusaa',
                'jenis_id' => $jenis->id,
                'sales_user_id' => $admin->id,
                'confirm_similar_name' => 1,
            ])
            ->assertRedirect();

        $this->assertSame(2, Customer::count());
    }

    public function test_merge_moves_quotation_to_target_customer_and_deletes_source(): void
    {
        $admin = $this->makeAdminUser();
        $company = Company::create([
            'name' => 'Test Company',
            'alias' => 'TST',
        ]);
        $jenis = $this->makeJenis();

        $source = Customer::create([
            'name' => 'Glamp Nusa',
            'jenis_id' => $jenis->id,
            'sales_user_id' => $admin->id,
        ]);

        $target = Customer::create([
            'name' => 'PT Glamp Nusa Bali',
            'jenis_id' => $jenis->id,
            'sales_user_id' => $admin->id,
        ]);

        $quotation = Quotation::create([
            'company_id' => $company->id,
            'customer_id' => $source->id,
            'sales_user_id' => $admin->id,
            'number' => 'QO-TEST-0001',
            'date' => now()->toDateString(),
            'status' => 'draft',
            'total' => 100000,
            'tax_percent' => 0,
            'tax_amount' => 0,
            'currency' => 'IDR',
        ]);

        $this->actingAs($admin)
            ->post(route('customers.merge', $source), [
                'target_customer_id' => $target->id,
            ])
            ->assertRedirect(route('customers.show', $target));

        $this->assertDatabaseMissing('customers', ['id' => $source->id]);
        $this->assertDatabaseHas('quotations', [
            'id' => $quotation->id,
            'customer_id' => $target->id,
        ]);
    }
}
