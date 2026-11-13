<?php

namespace App\Models;

use Database\Factories\SalesOrderLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One variant on a sales order at an agreed unit price, which everyone in the organization may see.
 */
#[Fillable(['sales_order_id', 'product_variant_id', 'quantity_ordered', 'quantity_dispatched', 'unit_price'])]
class SalesOrderLine extends Model
{
    /** @use HasFactory<SalesOrderLineFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'integer',
            'quantity_dispatched' => 'integer',
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

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
