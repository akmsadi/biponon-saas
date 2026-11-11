<?php

namespace Tests\Feature\Inventory;

use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StockOverviewTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $user;

    private Warehouse $dhaka;

    private Warehouse $chattogram;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->withOrganization($this->organization)->create();
        $this->dhaka = Warehouse::factory()->for($this->organization)->create(['name' => 'Dhaka Main', 'code' => 'DHK']);
        $this->chattogram = Warehouse::factory()->for($this->organization)->create(['name' => 'Chattogram Depot', 'code' => 'CTG']);
    }

    private function balance(string $productName, string $sku, Warehouse $warehouse, int $quantity, ?int $reorderLevel = null): StockBalance
    {
        $product = Product::query()->firstOrCreate(
            ['organization_id' => $this->organization->id, 'name' => $productName],
            Product::factory()->for($this->organization)->raw(['name' => $productName]),
        );

        $variant = ProductVariant::query()->where('sku', $sku)->first()
            ?? ProductVariant::factory()->for($product)->create(['sku' => $sku, 'reorder_level' => $reorderLevel]);

        return StockBalance::factory()->create([
            'product_variant_id' => $variant->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => $quantity,
        ]);
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('inventory.stock.index'))->assertRedirect(route('login'));
    }

    public function test_it_lists_only_the_current_organizations_non_zero_balances_with_a_summary(): void
    {
        $this->balance('Panjabi', 'PNJ-M-RED', $this->dhaka, 10, reorderLevel: 3);
        $this->balance('Panjabi', 'PNJ-M-RED', $this->chattogram, 2);
        $this->balance('Saree', 'SAR-F-BLU', $this->dhaka, 0);
        StockBalance::factory()->create(['quantity' => 99]);

        $this->actingAs($this->user)
            ->get(route('inventory.stock.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('inventory/stock')
                ->has('balances.data', 2)
                ->where('balances.data.0.warehouse.code', 'CTG')
                ->where('balances.data.0.quantity', 2)
                ->where('balances.data.0.is_low', true)
                ->where('balances.data.1.warehouse.code', 'DHK')
                ->where('balances.data.1.is_low', false)
                ->where('summary', [
                    'total_units' => 12,
                    'variants_in_stock' => 1,
                    'warehouses_in_stock' => 2,
                    'low_stock' => 1,
                ])
                ->has('warehouses', 2));
    }

    public function test_variants_without_a_reorder_level_are_never_low(): void
    {
        $this->balance('Panjabi', 'PNJ-M-RED', $this->dhaka, 0);

        $this->actingAs($this->user)
            ->get(route('inventory.stock.index', ['zero' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('balances.data', 1)
                ->where('balances.data.0.is_low', false)
                ->where('summary.low_stock', 0));
    }

    public function test_it_filters_by_search_warehouse_and_low_stock(): void
    {
        $this->balance('Panjabi', 'PNJ-M-RED', $this->dhaka, 10, reorderLevel: 5);
        $this->balance('Panjabi', 'PNJ-M-RED', $this->chattogram, 5);
        $this->balance('Saree', 'SAR-F-BLU', $this->dhaka, 0, reorderLevel: 1);

        $skus = fn (array $query) => collect(
            $this->actingAs($this->user)->get(route('inventory.stock.index', $query))->viewData('page')['props']['balances']['data']
        )->map(fn (array $row) => $row['sku'].'@'.$row['warehouse']['code'])->all();

        $this->assertSame(['PNJ-M-RED@CTG', 'PNJ-M-RED@DHK'], $skus(['search' => 'pnj-m']));
        $this->assertSame([], $skus(['search' => 'saree']));
        $this->assertSame(['SAR-F-BLU@DHK'], $skus(['search' => 'saree', 'zero' => 1]));
        $this->assertSame(['PNJ-M-RED@CTG'], $skus(['warehouse' => $this->chattogram->id]));
        $this->assertSame(['PNJ-M-RED@CTG', 'SAR-F-BLU@DHK'], $skus(['low' => 1]));
    }

    public function test_users_without_an_organization_cannot_see_stock(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('inventory.stock.index'))
            ->assertForbidden();
    }
}
