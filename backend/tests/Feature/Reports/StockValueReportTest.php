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
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StockValueReportTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $manager;

    private Warehouse $dhaka;

    private Warehouse $chattogram;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->manager = User::factory()->withOrganization($this->organization, 'manager')->create();
        $this->dhaka = Warehouse::factory()->for($this->organization)->create(['name' => 'Dhaka Main', 'code' => 'DHK']);
        $this->chattogram = Warehouse::factory()->for($this->organization)->create(['name' => 'Chattogram Depot', 'code' => 'CTG']);
    }

    private function variant(string $productName, string $sku): ProductVariant
    {
        $product = Product::factory()->for($this->organization)->create(['name' => $productName]);

        return ProductVariant::factory()->for($product)->create(['sku' => $sku]);
    }

    private function onHand(ProductVariant $variant, Warehouse $warehouse, int $quantity): void
    {
        StockBalance::factory()->create([
            'product_variant_id' => $variant->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => $quantity,
        ]);
    }

    private function receipt(ProductVariant $variant, int $quantity, ?string $unitCost, string $type = 'receive'): void
    {
        StockMovement::factory()->create([
            'product_variant_id' => $variant->id,
            'to_warehouse_id' => $this->dhaka->id,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'movement_type' => $type,
        ]);
    }

    public function test_members_cannot_open_or_export_the_report(): void
    {
        $member = User::factory()->withOrganization($this->organization, 'member')->create();

        $this->actingAs($member)->get(route('reports.stock-value.index'))->assertForbidden();
        $this->actingAs($member)->get(route('reports.stock-value.export'))->assertForbidden();
    }

    public function test_it_values_stock_at_the_weighted_average_receipt_cost(): void
    {
        $panjabi = $this->variant('Panjabi', 'PNJ-M-RED');
        $this->receipt($panjabi, 10, '100.00');
        $this->receipt($panjabi, 30, '120.00');
        $this->receipt($panjabi, 5, null); // Plain Receive stock: no cost, ignored by the average.
        $this->onHand($panjabi, $this->dhaka, 7);
        $this->onHand($panjabi, $this->chattogram, 3);

        $saree = $this->variant('Saree', 'SAR-F-BLU');
        $this->receipt($saree, 4, '2500.50');
        $this->onHand($saree, $this->dhaka, 4);

        $this->actingAs($this->manager)
            ->get(route('reports.stock-value.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('reports/stock-value')
                ->has('rows.data', 2)
                // Highest value first: 4 × 2,500.50 = 10,002.00.
                ->where('rows.data.0.sku', 'SAR-F-BLU')
                ->where('rows.data.0.value', '10002.00')
                // (10 × 100 + 30 × 120) ÷ 40 = 115.00, across both warehouses: 10 × 115 = 1,150.00.
                ->where('rows.data.1.sku', 'PNJ-M-RED')
                ->where('rows.data.1.on_hand', 10)
                ->where('rows.data.1.average_cost', '115.00')
                ->where('rows.data.1.value', '1150.00')
                ->where('summary', [
                    'total_value' => '11152.00',
                    'costed_units' => 14,
                    'uncosted_units' => 0,
                    'uncosted_variants' => 0,
                ]));
    }

    public function test_variants_never_received_with_a_cost_are_shown_as_uncosted_not_zero(): void
    {
        $costed = $this->variant('Panjabi', 'PNJ-M-RED');
        $this->receipt($costed, 2, '50.00');
        $this->onHand($costed, $this->dhaka, 2);

        $uncosted = $this->variant('Saree', 'SAR-F-BLU');
        $this->receipt($uncosted, 6, null);
        $this->receipt($uncosted, 6, '999.00', type: 'return'); // Only receipts count towards cost.
        $this->onHand($uncosted, $this->dhaka, 6);

        $this->actingAs($this->manager)
            ->get(route('reports.stock-value.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.data.1.sku', 'SAR-F-BLU')
                ->where('rows.data.1.average_cost', null)
                ->where('rows.data.1.value', null)
                ->where('summary', [
                    'total_value' => '100.00',
                    'costed_units' => 2,
                    'uncosted_units' => 6,
                    'uncosted_variants' => 1,
                ]));

        $this->actingAs($this->manager)
            ->get(route('reports.stock-value.index', ['uncosted' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows.data', 1)
                ->where('rows.data.0.sku', 'SAR-F-BLU')
                ->where('summary.uncosted_units', 6)
                ->where('summary.costed_units', 2));
    }

    public function test_it_filters_by_warehouse_and_search_and_ignores_other_organizations(): void
    {
        $panjabi = $this->variant('Panjabi', 'PNJ-M-RED');
        $this->receipt($panjabi, 10, '100.00');
        $this->onHand($panjabi, $this->dhaka, 7);
        $this->onHand($panjabi, $this->chattogram, 3);

        $saree = $this->variant('Saree', 'SAR-F-BLU');
        $this->onHand($saree, $this->chattogram, 0);

        StockBalance::factory()->create(['quantity' => 99]);

        $this->actingAs($this->manager)
            ->get(route('reports.stock-value.index', ['warehouse' => $this->chattogram->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows.data', 1)
                ->where('rows.data.0.on_hand', 3)
                ->where('rows.data.0.value', '300.00')
                ->where('summary.total_value', '300.00'));

        $this->actingAs($this->manager)
            ->get(route('reports.stock-value.index', ['search' => 'sar']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows.data', 0)
                ->where('summary.total_value', '0.00'));
    }

    public function test_it_exports_the_filtered_rows_as_csv(): void
    {
        $panjabi = $this->variant('=Panjabi', 'PNJ-M-RED');
        $this->receipt($panjabi, 4, '12.50');
        $this->onHand($panjabi, $this->dhaka, 4);

        $saree = $this->variant('Saree', 'SAR-F-BLU');
        $this->onHand($saree, $this->dhaka, 2);

        $response = $this->actingAs($this->manager)->get(route('reports.stock-value.export'));

        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $lines = array_map(str_getcsv(...), explode("\n", trim(substr($response->streamedContent(), 3))));

        $this->assertSame(['Product', 'Size', 'Color', 'SKU', 'Units on hand', 'Average unit cost (BDT)', 'Value (BDT)'], $lines[0]);
        $this->assertSame(["'=Panjabi", $panjabi->size->name, $panjabi->color->name, 'PNJ-M-RED', '4', '12.50', '50.00'], $lines[1]);
        $this->assertSame(['Saree', $saree->size->name, $saree->color->name, 'SAR-F-BLU', '2', '', 'no cost'], $lines[2]);
        $this->assertCount(3, $lines);
    }
}
