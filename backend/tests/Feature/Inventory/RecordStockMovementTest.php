<?php

namespace Tests\Feature\Inventory;

use App\Actions\Inventory\RecordStockMovement;
use App\Models\ProductVariant;
use App\Models\StockBalance;
use App\Models\Warehouse;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class RecordStockMovementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_transfer_moves_quantity_between_warehouses(): void
    {
        $variant = ProductVariant::factory()->create();
        $from = Warehouse::factory()->create(['organization_id' => $variant->organization_id]);
        $to = Warehouse::factory()->create(['organization_id' => $variant->organization_id]);
        StockBalance::factory()->for($variant)->create(['organization_id' => $variant->organization_id, 'warehouse_id' => $from->id, 'quantity' => 10]);

        $this->record($variant, 'transfer', 4, $from, $to);

        $this->assertSame(6, $this->balance($variant, $from));
        $this->assertSame(4, $this->balance($variant, $to));
    }

    public function test_a_movement_cannot_take_a_balance_below_zero(): void
    {
        $variant = ProductVariant::factory()->create();
        $warehouse = Warehouse::factory()->create(['organization_id' => $variant->organization_id]);
        StockBalance::factory()->for($variant)->create(['organization_id' => $variant->organization_id, 'warehouse_id' => $warehouse->id, 'quantity' => 3]);

        try {
            DB::transaction(fn () => $this->record($variant, 'dispatch', 4, $warehouse, null));
            $this->fail('Expected the dispatch to be refused.');
        } catch (DomainException) {
            $this->assertSame(3, $this->balance($variant, $warehouse));
            $this->assertDatabaseCount('stock_movements', 0);
        }
    }

    public function test_warehouses_of_another_organization_are_refused(): void
    {
        $variant = ProductVariant::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        $this->record($variant, 'receive', 1, null, Warehouse::factory()->create());
    }

    private function record(ProductVariant $variant, string $type, int $quantity, ?Warehouse $from, ?Warehouse $to): void
    {
        app(RecordStockMovement::class)->handle($variant, $type, $quantity, $from, $to, null, now());
    }

    private function balance(ProductVariant $variant, Warehouse $warehouse): int
    {
        return StockBalance::where(['product_variant_id' => $variant->id, 'warehouse_id' => $warehouse->id])->value('quantity');
    }
}
