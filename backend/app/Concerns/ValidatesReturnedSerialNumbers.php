<?php

namespace App\Concerns;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Validator;

/**
 * Checks serial numbers typed for stock coming back from a customer: one per unit when given, not repeated
 * within the return (case-insensitive), and each a dispatched unit of that variant. When a sales order line is
 * given, the unit must also have been dispatched against that line.
 */
trait ValidatesReturnedSerialNumbers
{
    /**
     * @param  array<string, array{quantity: int, serials: list<string>, product_variant_id: int, sales_order_line_id?: int}>  $groups
     *                                                                                                                                  keyed by the error key that reports problems
     */
    protected function validateReturnedSerials(Validator $validator, Organization $organization, array $groups): void
    {
        /** @var array<string, true> $seen lower-cased serials already used in an earlier group */
        $seen = [];

        foreach ($groups as $errorKey => $group) {
            $serials = $group['serials'];

            if ($serials === []) {
                continue;
            }

            if (count($serials) !== $group['quantity']) {
                $validator->errors()->add($errorKey, __('Enter exactly :quantity serial numbers (one per unit), or none. You entered :count.', [
                    'quantity' => $group['quantity'],
                    'count' => count($serials),
                ]));

                continue;
            }

            $repeated = [];

            foreach ($serials as $serial) {
                $key = mb_strtolower($serial);

                if (isset($seen[$key])) {
                    $repeated[] = $serial;
                }

                $seen[$key] = true;
            }

            if ($repeated !== []) {
                $validator->errors()->add($errorKey, __('Repeated in this return: :serials', ['serials' => implode(', ', $repeated)]));

                continue;
            }

            $returnable = $organization->serialNumbers()
                ->whereIn('serial_number', $serials)
                ->where('product_variant_id', $group['product_variant_id'])
                ->where('status', 'dispatched')
                ->when($group['sales_order_line_id'] ?? null, fn (Builder $query, int $lineId) => $query
                    ->whereHas('stockMovements', fn (Builder $movements) => $movements
                        ->where('sales_order_line_id', $lineId)
                        ->where('movement_type', 'dispatch')))
                ->pluck('serial_number')
                ->map(fn (string $serial): string => mb_strtolower($serial))
                ->all();

            $unavailable = array_filter($serials, fn (string $serial): bool => ! in_array(mb_strtolower($serial), $returnable, true));

            if ($unavailable !== []) {
                $validator->errors()->add($errorKey, isset($group['sales_order_line_id'])
                    ? __('Not dispatched on this order: :serials', ['serials' => implode(', ', $unavailable)])
                    : __('Not dispatched units of this variant: :serials', ['serials' => implode(', ', $unavailable)]));
            }
        }
    }
}
