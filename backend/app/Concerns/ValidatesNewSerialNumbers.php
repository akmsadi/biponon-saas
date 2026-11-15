<?php

namespace App\Concerns;

use App\Models\Organization;
use Illuminate\Validation\Validator;

/**
 * Checks serial numbers typed for stock that is arriving: one per unit when given, unique within the
 * receipt (case-insensitive) and not already known to the organization.
 */
trait ValidatesNewSerialNumbers
{
    /**
     * Split serials typed one per line into a trimmed list without blank lines.
     *
     * @return list<string>
     */
    protected static function splitSerials(string $text): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', $text)),
            fn (string $serial): bool => $serial !== '',
        ));
    }

    /**
     * @param  array<string, array{quantity: int, serials: list<string>}>  $groups  keyed by the error key
     *                                                                              that reports problems
     */
    protected function validateNewSerials(Validator $validator, Organization $organization, array $groups): void
    {
        /** @var array<string, string> $seen error key keyed by lower-cased serial */
        $seen = [];

        foreach ($groups as $errorKey => ['quantity' => $quantity, 'serials' => $serials]) {
            if ($serials === []) {
                continue;
            }

            if (count($serials) !== $quantity) {
                $validator->errors()->add($errorKey, __('Enter exactly :quantity serial numbers (one per unit), or none. You entered :count.', [
                    'quantity' => $quantity,
                    'count' => count($serials),
                ]));
            }

            $repeated = [];

            foreach ($serials as $serial) {
                $key = mb_strtolower($serial);

                if (isset($seen[$key])) {
                    $repeated[] = $serial;
                }

                $seen[$key] = $errorKey;
            }

            if ($repeated !== []) {
                $validator->errors()->add($errorKey, __('Repeated in this receipt: :serials', ['serials' => implode(', ', $repeated)]));
            }
        }

        if ($seen === []) {
            return;
        }

        $known = $organization->serialNumbers()
            ->whereIn('serial_number', array_unique(array_merge(...array_column($groups, 'serials'))))
            ->pluck('serial_number');

        foreach ($known->groupBy(fn (string $serial): string => $seen[mb_strtolower($serial)]) as $errorKey => $serials) {
            $validator->errors()->add($errorKey, __('Already exist: :serials', ['serials' => $serials->implode(', ')]));
        }
    }
}
