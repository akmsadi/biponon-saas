<?php

namespace App\Concerns;

/**
 * Turns amounts summed in cents (to avoid floating-point drift) back into the decimal strings the pages expect.
 */
trait FormatsMoney
{
    /**
     * Cents as a decimal string with two places, e.g. 123456.4 → "1234.56".
     */
    protected function money(float $cents): string
    {
        return number_format(round($cents) / 100, 2, '.', '');
    }
}
