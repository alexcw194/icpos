<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DokumenRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithRole(string $role): User
    {
        $roleRow = Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($roleRow);

        return $user;
    }

    public function test_dokumen_user_is_redirected_from_dashboard_to_documents_index(): void
    {
        $dokumenUser = $this->makeUserWithRole('Dokumen');

        $this->actingAs($dokumenUser)
            ->get(route('dashboard'))
            ->assertRedirect(route('documents.index'));
    }

    public function test_dokumen_user_can_access_documents_pages(): void
    {
        $dokumenUser = $this->makeUserWithRole('Dokumen');

        $this->actingAs($dokumenUser)
            ->get(route('documents.index'))
            ->assertOk();

        $this->actingAs($dokumenUser)
            ->get(route('documents.pending'))
            ->assertOk();
    }

    public function test_dokumen_user_cannot_access_non_document_module(): void
    {
        $dokumenUser = $this->makeUserWithRole('Dokumen');

        $this->actingAs($dokumenUser)
            ->get(route('sales-orders.index'))
            ->assertRedirect(route('documents.index'));
    }

    public function test_dokumen_user_can_see_all_customers_on_list(): void
    {
        $dokumenUser = $this->makeUserWithRole('Dokumen');
        $salesA = $this->makeUserWithRole('Sales');
        $salesB = $this->makeUserWithRole('Sales');

        Customer::query()->create([
            'name' => 'Alpha Customer',
            'created_by' => $salesA->id,
            'sales_user_id' => $salesA->id,
        ]);
        Customer::query()->create([
            'name' => 'Beta Customer',
            'created_by' => $salesB->id,
            'sales_user_id' => $salesB->id,
        ]);

        $response = $this->actingAs($dokumenUser)->get(route('customers.index'));
        $response->assertOk();
        $response->assertSee('Alpha Customer');
        $response->assertSee('Beta Customer');
    }
}
