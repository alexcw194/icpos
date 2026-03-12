<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\ItemVariant;
use App\Models\ManufactureJob;
use App\Models\ManufactureRecipe;
use App\Models\StockLedger;
use App\Models\StockSummary;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DeliveryAutoManufactureTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $user->assignRole($role);

        return $user;
    }

    /**
     * @return array{company: Company, warehouse: Warehouse, customer: Customer, unit: Unit}
     */
    private function makeContext(bool $allowNegativeStock = false): array
    {
        $company = Company::create([
            'name' => 'Test Company',
            'alias' => 'TCO',
        ]);

        $warehouse = Warehouse::create([
            'company_id' => $company->id,
            'code' => 'MAIN',
            'name' => 'Main Warehouse',
            'allow_negative_stock' => $allowNegativeStock,
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'name' => 'PT Customer Test',
        ]);

        $unit = Unit::create([
            'code' => 'pcs',
            'name' => 'PCS',
            'is_active' => true,
        ]);

        return compact('company', 'warehouse', 'customer', 'unit');
    }

    private function makeItem(array $ctx, string $name, string $itemType = 'standard'): Item
    {
        return Item::create([
            'company_id' => $ctx['company']->id,
            'name' => $name,
            'sku' => 'ITM-' . strtoupper(substr(md5($name . microtime(true)), 0, 10)),
            'price' => 10000,
            'unit_id' => $ctx['unit']->id,
            'list_type' => 'retail',
            'item_type' => $itemType,
        ]);
    }

    private function setStock(array $ctx, Item $item, ?int $variantId, float $qty): void
    {
        ItemStock::updateOrCreate(
            [
                'company_id' => $ctx['company']->id,
                'warehouse_id' => $ctx['warehouse']->id,
                'item_id' => $item->id,
                'item_variant_id' => $variantId,
            ],
            [
                'qty_on_hand' => $qty,
            ]
        );

        StockSummary::updateOrCreate(
            [
                'company_id' => $ctx['company']->id,
                'warehouse_id' => $ctx['warehouse']->id,
                'item_id' => $item->id,
                'variant_id' => $variantId,
            ],
            [
                'qty_balance' => $qty,
                'uom' => 'pcs',
            ]
        );
    }

    private function makeDelivery(array $ctx, Item $item, ?int $variantId, float $qty): Delivery
    {
        $delivery = Delivery::create([
            'company_id' => $ctx['company']->id,
            'customer_id' => $ctx['customer']->id,
            'warehouse_id' => $ctx['warehouse']->id,
            'status' => Delivery::STATUS_DRAFT,
            'date' => now()->toDateString(),
            'reference' => 'SO/TEST/001',
        ]);

        $delivery->lines()->create([
            'item_id' => $item->id,
            'item_variant_id' => $variantId,
            'description' => $item->name,
            'unit' => 'pcs',
            'qty' => $qty,
            'qty_requested' => $qty,
            'qty_backordered' => 0,
        ]);

        return $delivery->fresh(['lines.item']);
    }

    private function currentStock(array $ctx, Item $item, ?int $variantId): float
    {
        return (float) ItemStock::query()
            ->where('company_id', $ctx['company']->id)
            ->where('warehouse_id', $ctx['warehouse']->id)
            ->where('item_id', $item->id)
            ->when($variantId !== null, fn ($q) => $q->where('item_variant_id', $variantId), fn ($q) => $q->whereNull('item_variant_id'))
            ->value('qty_on_hand');
    }

    public function test_post_delivery_kit_creates_auto_production_and_deducts_components(): void
    {
        $ctx = $this->makeContext(false);
        $admin = $this->makeAdmin();

        $kit = $this->makeItem($ctx, 'KIT FIREPACK', 'kit');
        $componentA = $this->makeItem($ctx, 'COMP A');
        $componentB = $this->makeItem($ctx, 'COMP B');

        ManufactureRecipe::create([
            'parent_item_id' => $kit->id,
            'component_item_id' => $componentA->id,
            'qty_required' => 2,
        ]);
        ManufactureRecipe::create([
            'parent_item_id' => $kit->id,
            'component_item_id' => $componentB->id,
            'qty_required' => 1,
        ]);

        $this->setStock($ctx, $componentA, null, 10);
        $this->setStock($ctx, $componentB, null, 10);
        $this->setStock($ctx, $kit, null, 0);

        $delivery = $this->makeDelivery($ctx, $kit, null, 3);

        $this->actingAs($admin)
            ->post(route('deliveries.post', $delivery))
            ->assertRedirect(route('deliveries.show', $delivery));

        $delivery->refresh();
        $this->assertSame(Delivery::STATUS_POSTED, $delivery->status);

        $job = ManufactureJob::query()->where('source_type', 'delivery')->where('source_id', $delivery->id)->first();
        $this->assertNotNull($job);
        $this->assertSame('production', $job->job_type);
        $this->assertTrue((bool) $job->is_auto);

        $this->assertEqualsWithDelta(4.0, $this->currentStock($ctx, $componentA, null), 0.0001);
        $this->assertEqualsWithDelta(7.0, $this->currentStock($ctx, $componentB, null), 0.0001);
        $this->assertEqualsWithDelta(0.0, $this->currentStock($ctx, $kit, null), 0.0001);

        $this->assertDatabaseHas('stock_ledgers', [
            'reference_type' => 'manufacture',
            'reference_id' => $job->id,
            'item_id' => $kit->id,
        ]);
        $this->assertDatabaseHas('stock_ledgers', [
            'reference_type' => 'delivery',
            'reference_id' => $delivery->id,
            'item_id' => $kit->id,
        ]);
    }

    public function test_post_delivery_kit_without_recipe_is_blocked(): void
    {
        $ctx = $this->makeContext(false);
        $admin = $this->makeAdmin();

        $kit = $this->makeItem($ctx, 'KIT NO RECIPE', 'kit');
        $this->setStock($ctx, $kit, null, 0);

        $delivery = $this->makeDelivery($ctx, $kit, null, 1);

        $this->actingAs($admin)
            ->post(route('deliveries.post', $delivery))
            ->assertRedirect(route('deliveries.show', $delivery))
            ->assertSessionHas('error');

        $delivery->refresh();
        $this->assertSame(Delivery::STATUS_DRAFT, $delivery->status);
        $this->assertDatabaseCount('manufacture_jobs', 0);
    }

    public function test_component_stock_rule_blocks_when_warehouse_disallows_negative(): void
    {
        $ctx = $this->makeContext(false);
        $admin = $this->makeAdmin();

        $kit = $this->makeItem($ctx, 'KIT NEG BLOCK', 'kit');
        $component = $this->makeItem($ctx, 'COMP NEG BLOCK');

        ManufactureRecipe::create([
            'parent_item_id' => $kit->id,
            'component_item_id' => $component->id,
            'qty_required' => 5,
        ]);

        $this->setStock($ctx, $component, null, 2);

        $delivery = $this->makeDelivery($ctx, $kit, null, 1);

        $this->actingAs($admin)
            ->post(route('deliveries.post', $delivery))
            ->assertRedirect(route('deliveries.show', $delivery))
            ->assertSessionHas('error');

        $delivery->refresh();
        $this->assertSame(Delivery::STATUS_DRAFT, $delivery->status);
        $this->assertDatabaseCount('manufacture_jobs', 0);
    }

    public function test_component_stock_rule_allows_negative_when_warehouse_allows_it(): void
    {
        $ctx = $this->makeContext(true);
        $admin = $this->makeAdmin();

        $kit = $this->makeItem($ctx, 'KIT NEG ALLOW', 'kit');
        $component = $this->makeItem($ctx, 'COMP NEG ALLOW');

        ManufactureRecipe::create([
            'parent_item_id' => $kit->id,
            'component_item_id' => $component->id,
            'qty_required' => 5,
        ]);

        $this->setStock($ctx, $component, null, 2);
        $this->setStock($ctx, $kit, null, 0);

        $delivery = $this->makeDelivery($ctx, $kit, null, 1);

        $this->actingAs($admin)
            ->post(route('deliveries.post', $delivery))
            ->assertRedirect(route('deliveries.show', $delivery));

        $delivery->refresh();
        $this->assertSame(Delivery::STATUS_POSTED, $delivery->status);
        $this->assertEqualsWithDelta(-3.0, $this->currentStock($ctx, $component, null), 0.0001);
    }

    public function test_variant_kit_uses_same_recipe_and_posts_to_variant_stock(): void
    {
        $ctx = $this->makeContext(false);
        $admin = $this->makeAdmin();

        $kit = $this->makeItem($ctx, 'KIT VARIANT', 'kit');
        $kitVariant = ItemVariant::create([
            'item_id' => $kit->id,
            'sku' => 'KIT-VAR-001',
            'price' => 0,
            'stock' => 0,
            'attributes' => ['color' => 'red'],
            'is_active' => true,
            'min_stock' => 0,
        ]);
        $component = $this->makeItem($ctx, 'COMP VARIANT');

        ManufactureRecipe::create([
            'parent_item_id' => $kit->id,
            'component_item_id' => $component->id,
            'qty_required' => 1,
        ]);

        $this->setStock($ctx, $component, null, 2);
        $this->setStock($ctx, $kit, $kitVariant->id, 0);

        $delivery = $this->makeDelivery($ctx, $kit, $kitVariant->id, 1);

        $this->actingAs($admin)
            ->post(route('deliveries.post', $delivery))
            ->assertRedirect(route('deliveries.show', $delivery));

        $this->assertEqualsWithDelta(1.0, $this->currentStock($ctx, $component, null), 0.0001);
        $this->assertEqualsWithDelta(0.0, $this->currentStock($ctx, $kit, $kitVariant->id), 0.0001);

        $job = ManufactureJob::query()->where('source_type', 'delivery')->where('source_id', $delivery->id)->firstOrFail();

        $this->assertDatabaseHas('stock_ledgers', [
            'reference_type' => 'manufacture',
            'reference_id' => $job->id,
            'item_id' => $kit->id,
            'item_variant_id' => $kitVariant->id,
            'qty_change' => 1,
        ]);
    }

    public function test_cancel_delivery_without_reverse_keeps_auto_job_and_component_consumption(): void
    {
        $ctx = $this->makeContext(false);
        $admin = $this->makeAdmin();

        $kit = $this->makeItem($ctx, 'KIT CANCEL NO REVERSE', 'kit');
        $component = $this->makeItem($ctx, 'COMP CANCEL NO REVERSE');

        ManufactureRecipe::create([
            'parent_item_id' => $kit->id,
            'component_item_id' => $component->id,
            'qty_required' => 2,
        ]);

        $this->setStock($ctx, $component, null, 5);
        $this->setStock($ctx, $kit, null, 0);

        $delivery = $this->makeDelivery($ctx, $kit, null, 2);

        $this->actingAs($admin)->post(route('deliveries.post', $delivery));
        $this->actingAs($admin)
            ->post(route('deliveries.cancel', $delivery), ['reason' => 'Cancel test'])
            ->assertRedirect(route('deliveries.show', $delivery));

        $delivery->refresh();
        $this->assertSame(Delivery::STATUS_CANCELLED, $delivery->status);

        $job = ManufactureJob::query()->where('source_type', 'delivery')->where('source_id', $delivery->id)->firstOrFail();
        $this->assertNull($job->reversed_at);

        $this->assertEqualsWithDelta(1.0, $this->currentStock($ctx, $component, null), 0.0001);
        $this->assertEqualsWithDelta(2.0, $this->currentStock($ctx, $kit, null), 0.0001);
    }

    public function test_cancel_delivery_with_reverse_reverts_auto_manufacture_and_is_idempotent(): void
    {
        $ctx = $this->makeContext(false);
        $admin = $this->makeAdmin();

        $kit = $this->makeItem($ctx, 'KIT CANCEL REVERSE', 'kit');
        $component = $this->makeItem($ctx, 'COMP CANCEL REVERSE');

        ManufactureRecipe::create([
            'parent_item_id' => $kit->id,
            'component_item_id' => $component->id,
            'qty_required' => 2,
        ]);

        $this->setStock($ctx, $component, null, 6);
        $this->setStock($ctx, $kit, null, 0);

        $delivery = $this->makeDelivery($ctx, $kit, null, 2);

        $this->actingAs($admin)->post(route('deliveries.post', $delivery));

        $this->actingAs($admin)
            ->post(route('deliveries.cancel', $delivery), [
                'reason' => 'Cancel with reverse',
                'reverse_auto_manufacture' => '1',
            ])
            ->assertRedirect(route('deliveries.show', $delivery));

        $delivery->refresh();
        $this->assertSame(Delivery::STATUS_CANCELLED, $delivery->status);

        $job = ManufactureJob::query()->where('source_type', 'delivery')->where('source_id', $delivery->id)->firstOrFail();
        $this->assertNotNull($job->reversed_at);

        $this->assertEqualsWithDelta(6.0, $this->currentStock($ctx, $component, null), 0.0001);
        $this->assertEqualsWithDelta(0.0, $this->currentStock($ctx, $kit, null), 0.0001);

        $this->assertDatabaseHas('stock_ledgers', [
            'reference_type' => 'manufacture_reverse',
            'reference_id' => $job->id,
        ]);

        $beforeCount = StockLedger::query()
            ->where('reference_type', 'manufacture_reverse')
            ->where('reference_id', $job->id)
            ->count();

        $this->actingAs($admin)
            ->post(route('deliveries.cancel', $delivery), [
                'reason' => 'Retry cancel',
                'reverse_auto_manufacture' => '1',
            ])
            ->assertRedirect(route('deliveries.show', $delivery));

        $afterCount = StockLedger::query()
            ->where('reference_type', 'manufacture_reverse')
            ->where('reference_id', $job->id)
            ->count();

        $this->assertSame($beforeCount, $afterCount);
    }
}
