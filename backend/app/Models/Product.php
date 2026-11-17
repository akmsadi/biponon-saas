<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

#[Fillable(['organization_id', 'name', 'slug', 'description', 'is_active', 'show_in_shop'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // The slug is the product's online shop URL, so it is set once and kept when the product is renamed.
        static::creating(function (Product $product): void {
            $product->slug ??= self::uniqueSlug($product->organization_id, $product->name);
        });
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'show_in_shop' => 'boolean',
        ];
    }

    /**
     * A URL slug from the name that no other product of the organization uses: "classic-tee", then "classic-tee-2"…
     */
    public static function uniqueSlug(int $organizationId, string $name): string
    {
        $base = Str::slug($name) ?: 'product';
        $taken = self::query()
            ->where('organization_id', $organizationId)
            ->where(fn ($query) => $query->where('slug', $base)->orWhere('slug', 'like', "{$base}-%"))
            ->pluck('slug')
            ->flip();

        $slug = $base;

        for ($suffix = 2; $taken->has($slug); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }

    /**
     * Products the online shop lists: active, chosen for the shop, with at least one orderable variant.
     */
    #[Scope]
    protected function listedInShop(Builder $query): void
    {
        $query->where('is_active', true)
            ->where('show_in_shop', true)
            ->whereHas('productVariants', fn (Builder $variants) => $variants->orderableInShop());
    }

    /**
     * Shop photos, main photo first.
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
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
