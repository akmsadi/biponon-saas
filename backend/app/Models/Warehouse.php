<?php

namespace App\Models;

use Database\Factories\WarehouseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'name', 'code', 'address_line', 'city', 'country_code', 'is_active'])]
class Warehouse extends Model
{
    /** @use HasFactory<WarehouseFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function stockBalances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    /**
     * Whether this warehouse has ever held stock or appeared in a movement.
     */
    public function hasStockHistory(): bool
    {
        return $this->stockBalances()->exists()
            || $this->fromStockMovements()->exists()
            || $this->toStockMovements()->exists()
            || $this->serialNumbers()->exists();
    }

    public function serialNumbers(): HasMany
    {
        return $this->hasMany(SerialNumber::class, 'current_warehouse_id');
    }

    public function fromStockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'from_warehouse_id');
    }

    public function toStockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'to_warehouse_id');
    }
}
