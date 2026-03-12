<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\StockAdjustment;
use App\Models\StockLedger;
use App\Models\StockSummary;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StockAdjustmentDeleteTest extends TestCase
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

    /**
     * @return array{company: Company, warehouse: Warehouse, item: Item}
     */
    private function makeStockContext(): array
    {
        $company = Company::create([
            'name' => 'Test Company',
            'alias' => 'TCO',
        ]);

        $unit = Unit::create([
            'code' => 'pcs',
            'name' => 'PCS',
            'is_active' => true,
        ]);

        $item = Item::create([
            'name' => 'Test Item',
            'sku' => 'ITM-DEL-' . strtoupper(substr(md5((string) microtime(true)), 0, 8)),
            'price' => 1000,
            'unit_id' => $unit->id,
            'list_type' => 'retail',
        ]);

        $warehouse = Warehouse::create([
            'company_id' => $company->id,
            'code' => 'MAIN',
            'name' => 'Main Warehouse',
            'allow_negative_stock' => false,
            'is_active' => true,
        ]);

        return compact('company', 'warehouse', 'item');
    }

    private function createAdjustmentAndPost(
        array $ctx,
        float $qtyAdjustment,
        int $userId,
        ?Carbon $when = null
    ): StockAdjustment {
        $when = $when ?: now();

        $adjustment = StockAdjustment::create([
            'company_id' => $ctx['company']->id,
            'warehouse_id' => $ctx['warehouse']->id,
            'item_id' => $ctx['item']->id,
            'variant_id' => null,
            'qty_adjustment' => $qtyAdjustment,
            'reason' => 'Test adjustment',
            'created_by' => $userId,
        ]);

        $adjustment->created_at = $when->copy();
        $adjustment->updated_at = $when->copy();
        $adjustment->save();

        app(StockService::class)->manualAdjust(
            companyId: (int) $ctx['company']->id,
            warehouseId: (int) $ctx['warehouse']->id,
            itemId: (int) $ctx['item']->id,
            variantId: null,
            qtyAdjustment: $qtyAdjustment,
            reason: 'Test adjustment',
            referenceId: (int) $adjustment->id,
            ledgerDate: $adjustment->created_at,
            actingUserId: $userId
        );

        return $adjustment->fresh();
    }

    public function test_non_admin_cannot_delete_stock_adjustment(): void
    {
        $ctx = $this->makeStockContext();
        $admin = $this->makeUserWithRole('Admin');
        $normalUser = $this->makeUserWithRole();

        $target = $this->createAdjustmentAndPost($ctx, 5, $admin->id);

        $this->actingAs($normalUser)
            ->delete(route('inventory.adjustments.destroy', $target))
            ->assertStatus(403);

        $this->assertDatabaseHas('stock_adjustments', ['id' => $target->id]);
    }

    public function test_admin_can_delete_adjustment_and_update_stock_and_summary(): void
    {
        $ctx = $this->makeStockContext();
        $admin = $this->makeUserWithRole('Admin');

        $target = $this->createAdjustmentAndPost($ctx, 12, $admin->id, now()->subSeconds(10));

        $this->actingAs($admin)
            ->delete(route('inventory.adjustments.destroy', [
                'adjustment' => $target->id,
                'list_type' => 'retail',
            ]))
            ->assertRedirect(route('inventory.adjustments.index', ['list_type' => 'retail']));

        $this->assertDatabaseMissing('stock_adjustments', ['id' => $target->id]);
        $this->assertDatabaseMissing('stock_ledgers', [
            'reference_type' => 'manual_adjustment',
            'reference_id' => $target->id,
        ]);

        $stock = ItemStock::query()
            ->where('company_id', $ctx['company']->id)
            ->where('warehouse_id', $ctx['warehouse']->id)
            ->where('item_id', $ctx['item']->id)
            ->whereNull('item_variant_id')
            ->firstOrFail();

        $summary = StockSummary::query()
            ->where('company_id', $ctx['company']->id)
            ->where('warehouse_id', $ctx['warehouse']->id)
            ->where('item_id', $ctx['item']->id)
            ->whereNull('variant_id')
            ->firstOrFail();

        $this->assertEqualsWithDelta(0.0, (float) $stock->qty_on_hand, 0.0001);
        $this->assertEqualsWithDelta(0.0, (float) $summary->qty_balance, 0.0001);
    }

    public function test_delete_recomputes_balance_after_for_remaining_ledgers(): void
    {
        $ctx = $this->makeStockContext();
        $admin = $this->makeUserWithRole('SuperAdmin');

        $t0 = now()->subMinutes(5);
        $first = $this->createAdjustmentAndPost($ctx, 10, $admin->id, $t0->copy()->addSeconds(1));
        $target = $this->createAdjustmentAndPost($ctx, 5, $admin->id, $t0->copy()->addSeconds(2));
        $last = $this->createAdjustmentAndPost($ctx, -3, $admin->id, $t0->copy()->addSeconds(3));

        $this->actingAs($admin)
            ->delete(route('inventory.adjustments.destroy', $target))
            ->assertRedirect(route('inventory.adjustments.index'));

        $this->assertDatabaseMissing('stock_adjustments', ['id' => $target->id]);
        $this->assertDatabaseHas('stock_adjustments', ['id' => $first->id]);
        $this->assertDatabaseHas('stock_adjustments', ['id' => $last->id]);

        $currentStock = (float) ItemStock::query()
            ->where('company_id', $ctx['company']->id)
            ->where('warehouse_id', $ctx['warehouse']->id)
            ->where('item_id', $ctx['item']->id)
            ->whereNull('item_variant_id')
            ->value('qty_on_hand');

        $remaining = StockLedger::query()
            ->where('company_id', $ctx['company']->id)
            ->where('warehouse_id', $ctx['warehouse']->id)
            ->where('item_id', $ctx['item']->id)
            ->whereNull('item_variant_id')
            ->orderBy('ledger_date')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $remaining);
        $this->assertDatabaseMissing('stock_ledgers', [
            'reference_type' => 'manual_adjustment',
            'reference_id' => $target->id,
        ]);

        $totalChange = (float) $remaining->sum(fn (StockLedger $row) => (float) $row->qty_change);
        $running = $currentStock - $totalChange;

        foreach ($remaining as $row) {
            $running += (float) $row->qty_change;
            $this->assertEqualsWithDelta($running, (float) $row->balance_after, 0.0001);
        }
    }

    public function test_delete_allows_negative_stock_after_rollback(): void
    {
        $ctx = $this->makeStockContext();
        $admin = $this->makeUserWithRole('Admin');

        $target = $this->createAdjustmentAndPost($ctx, 10, $admin->id, now()->subSeconds(10));
        $this->createAdjustmentAndPost($ctx, -8, $admin->id, now()->subSeconds(5));

        $this->actingAs($admin)
            ->delete(route('inventory.adjustments.destroy', $target))
            ->assertRedirect(route('inventory.adjustments.index'));

        $stock = (float) ItemStock::query()
            ->where('company_id', $ctx['company']->id)
            ->where('warehouse_id', $ctx['warehouse']->id)
            ->where('item_id', $ctx['item']->id)
            ->whereNull('item_variant_id')
            ->value('qty_on_hand');

        $summary = (float) StockSummary::query()
            ->where('company_id', $ctx['company']->id)
            ->where('warehouse_id', $ctx['warehouse']->id)
            ->where('item_id', $ctx['item']->id)
            ->whereNull('variant_id')
            ->value('qty_balance');

        $this->assertEqualsWithDelta(-8.0, $stock, 0.0001);
        $this->assertEqualsWithDelta(-8.0, $summary, 0.0001);
    }
}

