<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Organization;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Models\StockMovement;

#[Fillable(['organization_id', 'product_variant_id', 'current_warehouse_id', 'serial_number', 'status', 'received_at'])]
class SerialNumber extends Model
{
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

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
