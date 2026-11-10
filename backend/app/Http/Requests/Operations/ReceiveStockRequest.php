<?php

namespace App\Http\Requests\Operations;

use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates a stock receipt: one active warehouse and up to 50 lines of active variants with quantities,
 * each optionally carrying exactly one serial number per unit.
 */
class ReceiveStockRequest extends FormRequest
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
            'received_at' => ['required', 'date', 'before_or_equal:+5 minutes'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.product_variant_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists(ProductVariant::class, 'id')
                    ->where('organization_id', $organization->id)
                    ->where('is_active', true)
                    ->where(fn (Builder $query) => $query->whereIn(
                        'product_id',
                        Product::select('id')->where('organization_id', $organization->id)->where('is_active', true),
                    )),
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
            'received_at.before_or_equal' => __('The received date cannot be in the future.'),
            'lines.*.product_variant_id.distinct' => __('This variant is already on another line. Combine the quantities instead.'),
            'lines.*.product_variant_id.exists' => __('Choose an active variant of an active product.'),
        ];
    }

    /**
     * Check serials: one per unit when given, unique within the receipt and not already known to the organization.
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

                /** @var array<string, int> $seen line index keyed by lower-cased serial */
                $seen = [];

                foreach ($this->input('lines', []) as $index => $line) {
                    $serials = $line['serials'] ?? [];

                    if ($serials === []) {
                        continue;
                    }

                    if (count($serials) !== (int) $line['quantity']) {
                        $validator->errors()->add("lines.{$index}.serials", __('Enter exactly :quantity serial numbers (one per unit), or none. You entered :count.', [
                            'quantity' => $line['quantity'],
                            'count' => count($serials),
                        ]));
                    }

                    $repeated = [];

                    foreach ($serials as $serial) {
                        $key = mb_strtolower($serial);

                        if (isset($seen[$key])) {
                            $repeated[] = $serial;
                        }

                        $seen[$key] = $index;
                    }

                    if ($repeated !== []) {
                        $validator->errors()->add("lines.{$index}.serials", __('Repeated in this receipt: :serials', ['serials' => implode(', ', $repeated)]));
                    }
                }

                $this->rejectKnownSerials($validator, $seen);
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
     * @param  array<string, int>  $seen  line index keyed by lower-cased serial
     */
    private function rejectKnownSerials(Validator $validator, array $seen): void
    {
        if ($seen === []) {
            return;
        }

        $serialsByLine = [];

        foreach ($this->input('lines') as $index => $line) {
            foreach ($line['serials'] ?? [] as $serial) {
                $serialsByLine[$serial] = $index;
            }
        }

        $known = $this->currentOrganization()->serialNumbers()
            ->whereIn('serial_number', array_keys($serialsByLine))
            ->pluck('serial_number');

        foreach ($known->groupBy(fn (string $serial): int => $serialsByLine[$serial] ?? $seen[mb_strtolower($serial)]) as $index => $serials) {
            $validator->errors()->add("lines.{$index}.serials", __('Already exist: :serials', ['serials' => $serials->implode(', ')]));
        }
    }

    private function currentOrganization(): Organization
    {
        return $this->attributes->get('organization');
    }
}
