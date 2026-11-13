<?php

namespace App\Concerns;

use App\Models\Organization;
use Illuminate\Validation\Validator;

/**
 * Checks serial numbers typed for stock that is leaving a warehouse: one per unit when given, not repeated
 * within the dispatch (case-insensitive), and each a unit of that variant in stock in that warehouse.
 */
trait ValidatesOutgoingSerialNumbers
{
    /**
     * @param  array<string, array{quantity: int, serials: list<string>, product_variant_id: int, warehouse_id: int, warehouse_code: string}>  $groups
     *                                                                                                                                                  keyed by the error key that reports problems
     */
    protected function validateOutgoingSerials(Validator $validator, Organization $organization, array $groups): void
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
                $validator->errors()->add($errorKey, __('Repeated in this dispatch: :serials', ['serials' => implode(', ', $repeated)]));

                continue;
            }

            $dispatchable = $organization->serialNumbers()
                ->whereIn('serial_number', $serials)
                ->where('product_variant_id', $group['product_variant_id'])
                ->where('current_warehouse_id', $group['warehouse_id'])
                ->where('status', 'in_stock')
                ->pluck('serial_number')
                ->map(fn (string $serial): string => mb_strtolower($serial))
                ->all();

            $unavailable = array_filter($serials, fn (string $serial): bool => ! in_array(mb_strtolower($serial), $dispatchable, true));

            if ($unavailable !== []) {
                $validator->errors()->add($errorKey, __('Not in stock in :warehouse for this variant: :serials', [
                    'warehouse' => $group['warehouse_code'],
                    'serials' => implode(', ', $unavailable),
                ]));
            }
        }
    }
}
