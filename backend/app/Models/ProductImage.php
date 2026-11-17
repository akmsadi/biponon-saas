<?php

namespace App\Models;

use Database\Factories\ProductImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A product photo on the public disk. The image with the lowest sort_order is the product's main photo.
 */
#[Fillable(['organization_id', 'product_id', 'path', 'sort_order'])]
class ProductImage extends Model
{
    /** @use HasFactory<ProductImageFactory> */
    use HasFactory;

    public const DISK = 'public';

    /** Most photos one product can have. */
    public const MAX_PER_PRODUCT = 8;

    protected static function booted(): void
    {
        // Single deletes remove the file too; whole products clean up their folder instead.
        static::deleted(fn (ProductImage $image) => Storage::disk(self::DISK)->delete($image->path));
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Public URL of the file.
     */
    public function url(): string
    {
        return Storage::disk(self::DISK)->url($this->path);
    }

    /**
     * Folder that holds all of one product's photos.
     */
    public static function folderFor(Product $product): string
    {
        return "products/{$product->organization_id}/{$product->id}";
    }
}
