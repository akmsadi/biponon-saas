<?php

namespace App\Models;

use Database\Factories\PurchaseOrderLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One variant on a purchase order. The unit cost is optional and only managers and admins see it.
 */
#[Fillable(['purchase_order_id', 'product_variant_id', 'quantity_ordered', 'quantity_received', 'unit_cost'])]
class PurchaseOrderLine extends Model
{
    /** @use HasFactory<PurchaseOrderLineFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'integer',
            'quantity_received' => 'integer',
            'unit_cost' => 'decimal:2',
        ];
    }

    /**
     * Quantity × unit cost in whole cents, or null when the cost is unknown. Costs are capped at
     * 99,999,999.99 so this stays exact.
     */
    public function totalCents(): ?int
    {
        return $this->unit_cost === null
            ? null
            : (int) round((float) $this->unit_cost * 100) * $this->quantity_ordered;
    }

    /**
     * Units still expected from the supplier.
     */
    public function quantityRemaining(): int
    {
        return max(0, $this->quantity_ordered - $this->quantity_received);
    }

    /**
     * The receipt movements that brought this line's stock in.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
