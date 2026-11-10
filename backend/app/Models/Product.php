<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['organization_id', 'name', 'description', 'is_active'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
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

    public function productVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function stockBalances(): HasManyThrough
    {
        return $this->hasManyThrough(StockBalance::class, ProductVariant::class);
    }

    public function stockMovements(): HasManyThrough
    {
        return $this->hasManyThrough(StockMovement::class, ProductVariant::class);
    }

    public function serialNumbers(): HasManyThrough
    {
        return $this->hasManyThrough(SerialNumber::class, ProductVariant::class);
    }

    /**
     * Whether stock of any of this product's variants has ever been recorded anywhere.
     */
    public function hasStockHistory(): bool
    {
        return $this->stockMovements()->exists()
            || $this->stockBalances()->exists()
            || $this->serialNumbers()->exists();
    }
}
