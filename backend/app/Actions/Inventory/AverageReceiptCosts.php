<?php

namespace App\Actions\Inventory;

use App\Models\Organization;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;

/**
 * The inputs for each variant's weighted average cost, shared by the reports that value stock: the units received
 * with a cost and what they cost in total, in cents. Average = cost_cents ÷ costed_quantity. Only receipts with a
 * unit cost (receipts against purchase orders) count.
 */
class AverageReceiptCosts
{
    /**
     * A query with one row per variant: product_variant_id, costed_quantity, cost_cents. Join it as a subquery.
     *
     * @return Builder<StockMovement>
     */
    public function query(Organization $organization): Builder
    {
        return StockMovement::query()
            ->where('organization_id', $organization->id)
            ->where('movement_type', 'receive')
            ->whereNotNull('unit_cost')
            ->where('quantity', '>', 0)
            ->groupBy('product_variant_id')
            ->select('product_variant_id')
            ->selectRaw('SUM(quantity) as costed_quantity')
            ->selectRaw('SUM(quantity * ROUND(unit_cost * 100)) as cost_cents');
    }
}
