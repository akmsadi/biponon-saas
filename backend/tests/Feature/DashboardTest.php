<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->withOrganization()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_users_without_an_active_organization_are_sent_to_the_no_organization_page()
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))->assertRedirect(route('no-organization'));

        $this->get(route('no-organization'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/no-organization'));
    }

    public function test_members_of_an_active_organization_skip_the_no_organization_page()
    {
        $this->actingAs(User::factory()->withOrganization()->create());

        $this->get(route('no-organization'))->assertRedirect(route('dashboard'));
    }

    public function test_the_dashboard_summarizes_stock_flow_low_stock_movements_and_warehouses(): void
    {
        $this->travelTo(Carbon::parse('2026-11-12 10:30:00', 'UTC'));

        $organization = Organization::factory()->create();
        $user = User::factory()->withOrganization($organization, 'member')->create(['name' => 'Sam']);
        $main = Warehouse::factory()->for($organization)->create(['name' => 'Main', 'code' => 'MAIN']);
        $shop = Warehouse::factory()->for($organization)->create(['name' => 'Shop', 'code' => 'SHOP']);
        $product = Product::factory()->for($organization)->create(['name' => 'Panjabi']);
        $low = ProductVariant::factory()->for($product)->create(['sku' => 'LOW', 'reorder_level' => 5]);
        $lower = ProductVariant::factory()->for($product)->create(['sku' => 'LOWER', 'reorder_level' => 10]);
        $fine = ProductVariant::factory()->for($product)->create(['sku' => 'FINE', 'reorder_level' => 1]);

        $this->balance($low, $main, 4);
        $this->balance($lower, $shop, 2);
        $this->balance($fine, $main, 30);

        $this->movement($fine, null, $main, 30, 'receive', '2026-11-09 08:15:00', $user);
        $this->movement($fine, $main, null, 6, 'dispatch', '2026-11-11 12:00:00');
        $this->movement($fine, null, $main, 2, 'return', '2026-11-11 12:40:00');
        $this->movement($fine, $main, $shop, 9, 'transfer', '2026-11-12 09:00:00');
        $this->movement($fine, null, $main, 100, 'receive', '2026-10-25 00:00:00');
        $this->movement($fine, null, $main, 500, 'receive', '2026-09-01 00:00:00');

        $foreign = ProductVariant::factory()->create();
        $this->movement($foreign, null, Warehouse::factory()->for($foreign->organization)->create(), 999, 'receive', '2026-11-11 00:00:00');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('summary', ['total_units' => 36, 'variants_in_stock' => 3, 'warehouses_in_stock' => 2, 'low_stock' => 2])
                ->where('lastSevenDays', ['units_in' => 32, 'units_out' => 6])
                ->where('hourlyFlow', [
                    ['hour' => '2026-10-25 00', 'units_in' => 100, 'units_out' => 0],
                    ['hour' => '2026-11-09 08', 'units_in' => 30, 'units_out' => 0],
                    ['hour' => '2026-11-11 12', 'units_in' => 2, 'units_out' => 6],
                    ['hour' => '2026-11-12 09', 'units_in' => 0, 'units_out' => 0],
                ])
                ->has('lowStock', 2)
                ->where('lowStock.0.sku', 'LOWER')
                ->where('lowStock.0.warehouse', 'SHOP')
                ->where('lowStock.1.sku', 'LOW')
                ->has('recentMovements', 6)
                ->where('recentMovements.0.type', 'transfer')
                ->where('recentMovements.0.from', 'MAIN')
                ->where('recentMovements.0.to', 'SHOP')
                ->where('recentMovements.5.performed_by', null)
                ->where('recentMovements.3.performed_by', 'Sam')
                ->where('warehouses', [
                    ['id' => $main->id, 'name' => 'Main', 'code' => 'MAIN', 'units' => 34],
                    ['id' => $shop->id, 'name' => 'Shop', 'code' => 'SHOP', 'units' => 2],
                ]));
    }

    private function balance(ProductVariant $variant, Warehouse $warehouse, int $quantity): void
    {
        StockBalance::factory()->create([
            'organization_id' => $variant->organization_id,
            'product_variant_id' => $variant->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => $quantity,
        ]);
    }

    private function movement(ProductVariant $variant, ?Warehouse $from, ?Warehouse $to, int $quantity, string $type, string $at, ?User $by = null): void
    {
        StockMovement::factory()->create([
            'product_variant_id' => $variant->id,
            'from_warehouse_id' => $from?->id,
            'to_warehouse_id' => $to?->id,
            'quantity' => $quantity,
            'movement_type' => $type,
            'occurred_at' => Carbon::parse($at, 'UTC'),
            'performed_by' => $by?->id,
        ]);
    }
}
