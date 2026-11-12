<?php

namespace App\Http\Requests\Operations;

use App\Models\Organization;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates a customer return: an active warehouse the units come back into and up to 50 lines of variants
 * (active or not), each optionally naming the serial numbers of previously dispatched units.
 */
class ReturnStockRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organization = $this->currentOrganization();

        return [
            'warehouse_id' => [
                'required',
                'integer',
                Rule::exists(Warehouse::class, 'id')
                    ->where('organization_id', $organization->id)
                    ->where('is_active', true),
            ],
            'returned_at' => ['required', 'date', 'before_or_equal:+5 minutes'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.product_variant_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists(ProductVariant::class, 'id')->where('organization_id', $organization->id),
            ],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'lines.*.serials' => ['array'],
            'lines.*.serials.*' => ['string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'warehouse_id.exists' => __('Choose an active warehouse to return the stock into.'),
            'returned_at.before_or_equal' => __('The return date cannot be in the future.'),
            'lines.*.product_variant_id.distinct' => __('This variant is already on another line. Combine the quantities instead.'),
        ];
    }

    /**
     * Check that any serials given are one per unit, not repeated, and are dispatched units of that variant.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->rejectInvalidSerials($validator, $this->input('lines'));
            },
        ];
    }

    /**
     * Split each line's serials text into a trimmed list without blank lines.
     */
    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines');

        if (! is_array($lines)) {
            return;
        }

        $this->merge([
            'lines' => array_map(function (mixed $line): mixed {
                if (is_array($line) && is_string($line['serials'] ?? null)) {
                    $line['serials'] = array_values(array_filter(
                        array_map('trim', preg_split('/\r\n|\r|\n/', $line['serials'])),
                        fn (string $serial): bool => $serial !== '',
                    ));
                }

                return $line;
            }, $lines),
        ]);
    }

    /**
     * @param  array<int, array{product_variant_id: int|string, quantity: int|string, serials?: list<string>}>  $lines
     */
    private function rejectInvalidSerials(Validator $validator, array $lines): void
    {
        /** @var array<string, true> $seen lower-cased serials already used on an earlier line */
        $seen = [];

        foreach ($lines as $index => $line) {
            $serials = $line['serials'] ?? [];

            if ($serials === []) {
                continue;
            }

            if (count($serials) !== (int) $line['quantity']) {
                $validator->errors()->add("lines.{$index}.serials", __('Enter exactly :quantity serial numbers (one per unit), or none. You entered :count.', [
                    'quantity' => $line['quantity'],
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
                $validator->errors()->add("lines.{$index}.serials", __('Repeated in this return: :serials', ['serials' => implode(', ', $repeated)]));

                continue;
            }

            $returnable = $this->currentOrganization()->serialNumbers()
                ->whereIn('serial_number', $serials)
                ->where('product_variant_id', $line['product_variant_id'])
                ->where('status', 'dispatched')
                ->pluck('serial_number')
                ->map(fn (string $serial): string => mb_strtolower($serial))
                ->all();

            $unavailable = array_filter($serials, fn (string $serial): bool => ! in_array(mb_strtolower($serial), $returnable, true));

            if ($unavailable !== []) {
                $validator->errors()->add("lines.{$index}.serials", __('Not dispatched units of this variant: :serials', [
                    'serials' => implode(', ', $unavailable),
                ]));
            }
        }
    }

    private function currentOrganization(): Organization
    {
        return $this->attributes->get('organization');
    }
}
