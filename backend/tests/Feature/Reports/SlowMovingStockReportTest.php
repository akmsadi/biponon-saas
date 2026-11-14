<?php

namespace Tests\Feature\Reports;

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

class SlowMovingStockReportTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $manager;

    private Warehouse $dhaka;

    private Warehouse $chattogram;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-14 04:00:00', 'UTC'));

        $this->organization = Organization::factory()->create();
        $this->manager = User::factory()->withOrganization($this->organization, 'manager')->create();
        $this->dhaka = Warehouse::factory()->for($this->organization)->create(['name' => 'Dhaka Main', 'code' => 'DHK']);
        $this->chattogram = Warehouse::factory()->for($this->organization)->create(['name' => 'Chattogram Depot', 'code' => 'CTG']);
    }

    private function variant(string $sku, int $onHand, ?Warehouse $warehouse = null): ProductVariant
    {
        $variant = ProductVariant::factory()
            ->for(Product::factory()->for($this->organization)->create(['name' => "Product {$sku}"]))
            ->create(['sku' => $sku]);

        StockBalance::factory()->create([
            'product_variant_id' => $variant->id,
            'warehouse_id' => ($warehouse ?? $this->dhaka)->id,
            'quantity' => $onHand,
        ]);

        return $variant;
    }

    private function movement(ProductVariant $variant, string $type, int $daysAgo, ?string $unitCost = null, ?Warehouse $from = null, ?Warehouse $to = null): void
    {
        StockMovement::factory()->create([
            'product_variant_id' => $variant->id,
            'movement_type' => $type,
            'quantity' => 1,
            'unit_cost' => $unitCost,
            'from_warehouse_id' => in_array($type, ['dispatch', 'transfer'], true) ? ($from ?? $this->dhaka)->id : null,
            'to_warehouse_id' => in_array($type, ['receive', 'transfer', 'return'], true) ? ($to ?? $this->dhaka)->id : null,
            'occurred_at' => now()->subDays($daysAgo),
        ]);
    }

    public function test_it_lists_stock_idle_for_the_chosen_days_longest_idle_first(): void
    {
        $stale = $this->variant('STALE', 5);
        $this->movement($stale, 'receive', 300, '10.00');
        $this->movement($stale, 'dispatch', 120);

        $neverSold = $this->variant('NEVER', 2);
        $this->movement($neverSold, 'receive', 200);

        $selling = $this->variant('SELLING', 9);
        $this->movement($selling, 'receive', 300);
        $this->movement($selling, 'dispatch', 10);

        $justArrived = $this->variant('NEW', 4);
        $this->movement($justArrived, 'receive', 5);

        $this->variant('SEEDED', 1); // Stock with no movement history at all.
        $this->variant('EMPTY', 0);

        $this->actingAs($this->manager)
            ->get(route('reports.slow-moving.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('reports/slow-moving')
                ->where('filters.days', 90)
                ->has('rows.data', 3)
                ->where('rows.data.0.sku', 'SEEDED')
                ->where('rows.data.0.days_idle', null)
                ->where('rows.data.1.sku', 'NEVER')
                ->where('rows.data.1.last_dispatched_at', null)
                ->where('rows.data.1.days_idle', 200)
                ->where('rows.data.2.sku', 'STALE')
                ->where('rows.data.2.days_idle', 120)
                ->where('rows.data.2.value', '50.00')
                ->where('summary', ['variants' => 3, 'units' => 8, 'value' => '50.00', 'uncosted_units' => 3]));

        $this->actingAs($this->manager)
            ->get(route('reports.slow-moving.index', ['days' => 180]))
            ->assertInertia(fn (Assert $page) => $page->has('rows.data', 2)->where('rows.data.1.sku', 'NEVER'));
    }

    public function test_a_warehouse_filter_counts_only_that_warehouses_stock_dispatches_and_arrivals(): void
    {
        $variant = $this->variant('PNJ', 3, $this->chattogram);
        $this->movement($variant, 'receive', 300);
        $this->movement($variant, 'dispatch', 5); // From Dhaka, so it doesn't make Chattogram's stock move.
        $this->movement($variant, 'transfer', 100, from: $this->dhaka, to: $this->chattogram);

        $this->actingAs($this->manager)
            ->get(route('reports.slow-moving.index'))
            ->assertInertia(fn (Assert $page) => $page->has('rows.data', 0));

        $this->actingAs($this->manager)
            ->get(route('reports.slow-moving.index', ['warehouse' => $this->chattogram->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows.data', 1)
                ->where('rows.data.0.last_dispatched_at', null)
                ->where('rows.data.0.days_idle', 100));
    }

    public function test_members_see_the_report_without_values(): void
    {
        $member = User::factory()->withOrganization($this->organization, 'member')->create();
        $variant = $this->variant('STALE', 5);
        $this->movement($variant, 'receive', 300, '10.00');

        $this->actingAs($member)
            ->get(route('reports.slow-moving.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canSeeCosts', false)
                ->has('rows.data', 1)
                ->missing('rows.data.0.value')
                ->missing('summary.value'));

        $this->assertStringNotContainsString('Value', $this->actingAs($member)->get(route('reports.slow-moving.export'))->streamedContent());
    }

    public function test_it_exports_slow_moving_rows_as_csv_with_dates_in_the_organizations_timezone(): void
    {
        $variant = $this->variant('STALE', 5);
        StockMovement::factory()->create([
            'product_variant_id' => $variant->id,
            'movement_type' => 'receive',
            'quantity' => 5,
            'unit_cost' => '10.00',
            'to_warehouse_id' => $this->dhaka->id,
            // 2026-01-01 in Dhaka, still 2025-12-31 in UTC.
            'occurred_at' => Carbon::parse('2025-12-31 20:00:00', 'UTC'),
        ]);

        $response = $this->actingAs($this->manager)->get(route('reports.slow-moving.export'));

        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $lines = array_map(str_getcsv(...), explode("\n", trim(substr($response->streamedContent(), 3))));

        $this->assertSame(['Product', 'Size', 'Color', 'SKU', 'Units on hand', 'Last dispatched', 'Last arrived', 'Idle since', 'Days idle', 'Value (BDT)'], $lines[0]);
        $this->assertSame(['Product STALE', $variant->size->name, $variant->color->name, 'STALE', '5', 'never', '2026-01-01', '2026-01-01', '317', '50.00'], $lines[1]);
        $this->assertCount(2, $lines);
    }
}
