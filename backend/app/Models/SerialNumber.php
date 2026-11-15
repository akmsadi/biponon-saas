<?php

namespace App\Models;

use Database\Factories\SerialNumberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Optional per-unit tracking on top of quantity stock: one physical unit with its own code.
 */
#[Fillable(['organization_id', 'product_variant_id', 'current_warehouse_id', 'serial_number', 'status', 'received_at'])]
class SerialNumber extends Model
{
    /** @use HasFactory<SerialNumberFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function currentWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * The movements this unit took part in, i.e. its own history.
     */
    public function stockMovements(): BelongsToMany
    {
        return $this->belongsToMany(StockMovement::class);
    }
}
