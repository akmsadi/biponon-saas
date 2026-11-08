<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Size;
use App\Models\Color;
use App\Models\SerialNumber;
use App\Models\StockMovement;

#[Fillable(['organization_id', 'product_id', 'size_id', 'color_id', 'sku', 'barcode', 'price', 'is_active'])]
class ProductVariant extends Model
{
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    public function serialNumbers(): HasMany
    {
        return $this->hasMany(SerialNumber::class);
    }

    public function stockMovements(): HasManyThrough
    {
        return $this->hasManyThrough(StockMovement::class, SerialNumber::class);
    }
}
