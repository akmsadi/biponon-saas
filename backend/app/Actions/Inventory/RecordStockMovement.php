<?php

namespace App\Actions\Inventory;

use App\Models\ProductVariant;
use App\Models\PurchaseOrderLine;
use App\Models\SalesOrderLine;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\CarbonInterface;
use DomainException;
use InvalidArgumentException;

/**
 * Records one ledger entry and applies it to the stock balances of the warehouses involved.
 *
 * Must run inside a database transaction: balance rows are locked for update so concurrent
 * movements of the same variant and warehouse cannot overwrite each other.
 */
class RecordStockMovement
{
    /**
     * A receipt against a purchase order passes its line, which is linked and whose unit cost is copied; a
     * dispatch against a sales order passes its line, which is linked.
     *
     * @throws DomainException when the movement would take a balance below zero
     */
    public function handle(
        ProductVariant $variant,
        string $movementType,
        int $quantity,
        ?Warehouse $fromWarehouse,
        ?Warehouse $toWarehouse,
        ?User $performedBy,
        CarbonInterface $occurredAt,
        ?string $notes = null,
        ?PurchaseOrderLine $purchaseOrderLine = null,
        ?SalesOrderLine $salesOrderLine = null,
    ): StockMovement {
        $this->ensureValid($variant, $quantity, $fromWarehouse, $toWarehouse);

        if ($fromWarehouse) {
            $this->adjustBalance($variant, $fromWarehouse, -$quantity);
        }

        if ($toWarehouse) {
            $this->adjustBalance($variant, $toWarehouse, $quantity);
        }

        return StockMovement::create([
            'organization_id' => $variant->organization_id,
            'product_variant_id' => $variant->id,
            'from_warehouse_id' => $fromWarehouse?->id,
            'to_warehouse_id' => $toWarehouse?->id,
            'quantity' => $quantity,
            'unit_cost' => $purchaseOrderLine?->unit_cost,
            'purchase_order_line_id' => $purchaseOrderLine?->id,
            'sales_order_line_id' => $salesOrderLine?->id,
            'movement_type' => $movementType,
            'performed_by' => $performedBy?->id,
            'notes' => $notes,
            'occurred_at' => $occurredAt,
        ]);
    }

    private function ensureValid(ProductVariant $variant, int $quantity, ?Warehouse $fromWarehouse, ?Warehouse $toWarehouse): void
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('A stock movement needs a positive quantity.');
        }

        if (! $fromWarehouse && ! $toWarehouse) {
            throw new InvalidArgumentException('A stock movement needs a source or a destination warehouse.');
        }

        foreach ([$fromWarehouse, $toWarehouse] as $warehouse) {
            if ($warehouse && $warehouse->organization_id !== $variant->organization_id) {
                throw new InvalidArgumentException('The warehouse and the variant belong to different organizations.');
            }
        }
    }

    private function adjustBalance(ProductVariant $variant, Warehouse $warehouse, int $delta): void
    {
        $keys = [
            'organization_id' => $variant->organization_id,
            'product_variant_id' => $variant->id,
            'warehouse_id' => $warehouse->id,
        ];

        StockBalance::createOrFirst($keys, ['quantity' => 0]);

        $balance = StockBalance::where($keys)->lockForUpdate()->firstOrFail();

        if ($balance->quantity + $delta < 0) {
            throw new DomainException(sprintf(
                'Only %d units of %s are in %s.',
                $balance->quantity,
                $variant->sku,
                $warehouse->code,
            ));
        }

        $balance->update(['quantity' => $balance->quantity + $delta]);
    }
}
