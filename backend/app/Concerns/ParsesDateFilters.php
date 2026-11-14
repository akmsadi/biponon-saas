<?php

namespace App\Concerns;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * Reads YYYY-MM-DD date filters from the query string as days in a given timezone, ignoring anything malformed.
 */
trait ParsesDateFilters
{
    /**
     * The start of the given day in the timezone, or null when the value is not a valid YYYY-MM-DD date.
     */
    protected function parseDate(string $value, string $timezone): ?Carbon
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            return Carbon::createFromFormat('!Y-m-d', $value, $timezone) ?: null;
        } catch (Throwable) {
            return null;
        }
    }
}
