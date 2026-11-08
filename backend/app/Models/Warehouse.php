<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\SerialNumber;
use App\Models\StockMovement;
use App\Models\Organization;

#[Fillable(['organization_id', 'name', 'code', 'address_line', 'city', 'country_code', 'is_active'])]
class Warehouse extends Model
{
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
