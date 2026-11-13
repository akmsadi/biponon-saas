<?php

namespace App\Models;

use Database\Factories\SalesOrderLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One variant on a sales order at an agreed unit price, which everyone in the organization may see.
 */
#[Fillable(['sales_order_id', 'product_variant_id', 'quantity_ordered', 'quantity_dispatched', 'quantity_returned', 'unit_price'])]
class SalesOrderLine extends Model
{
    /** @use HasFactory<SalesOrderLineFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'integer',
            'quantity_dispatched' => 'integer',
            'quantity_returned' => 'integer',
            'unit_price' => 'decimal:2',
        ];
    }

    /**
     * Quantity × unit price in whole cents. Prices are capped at 99,999,999.99 so this stays exact.
     */
    public function totalCents(): int
    {
        return (int) round((float) $this->unit_price * 100) * $this->quantity_ordered;
    }

    /**
     * Units still to be sent to the customer.
     */
    public function quantityRemaining(): int
    {
        return max(0, $this->quantity_ordered - $this->quantity_dispatched);
    }

    /**
     * Units sent that have not come back yet, so could still be returned.
     */
    public function quantityReturnable(): int
    {
        return max(0, $this->quantity_dispatched - $this->quantity_returned);
    }

    /**
     * The dispatch and return movements of this line's stock.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
