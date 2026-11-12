<?php

namespace App\Actions\Inventory;

use App\Models\Organization;
use App\Models\StockBalance;
use Illuminate\Database\Eloquent\Builder;

/**
 * Totals across all of an organization's stock, shared by the stock overview and the dashboard so both always
 * agree. A balance row is low stock when its quantity is at or below its variant's reorder level.
 */
class SummarizeStock
{
    /**
     * @return array{total_units: int, variants_in_stock: int, warehouses_in_stock: int, low_stock: int}
     */
    public function handle(Organization $organization): array
    {
        $inStock = $this->balances($organization)->where('quantity', '>', 0);

        return [
            'total_units' => (int) (clone $inStock)->sum('quantity'),
            'variants_in_stock' => (clone $inStock)->distinct()->count('product_variant_id'),
            'warehouses_in_stock' => (clone $inStock)->distinct()->count('warehouse_id'),
            'low_stock' => self::whereLowStock(
                $this->balances($organization)->join('product_variants', 'product_variants.id', '=', 'stock_balances.product_variant_id')
            )->count(),
        ];
    }

    /**
     * Limit a balance query (already joined to product_variants) to rows at or below the variant's reorder level.
     *
     * @template TQuery of Builder
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public static function whereLowStock(Builder $query): Builder
    {
        return $query
            ->whereNotNull('product_variants.reorder_level')
            ->whereColumn('stock_balances.quantity', '<=', 'product_variants.reorder_level');
    }

    /**
     * Start a query over the organization's stock balances with a qualified column, so it is safe to join.
     *
     * @return Builder<StockBalance>
     */
    private function balances(Organization $organization): Builder
    {
        return StockBalance::query()->where('stock_balances.organization_id', $organization->id);
    }
}
