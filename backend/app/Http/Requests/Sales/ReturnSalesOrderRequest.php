<?php

namespace App\Http\Requests\Sales;

use App\Concerns\ValidatesReturnedSerialNumbers;
use App\Models\Organization;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\Warehouse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates a customer return against a sales order: one active warehouse the units come back into, and per
 * order line a quantity (0 skips the line) no larger than was sent and not yet returned, optionally naming
 * the serial numbers of units dispatched on that line.
 */
class ReturnSalesOrderRequest extends FormRequest
{
    use ValidatesReturnedSerialNumbers;

    /**
     * Orders of another organization are not found.
     */
    public function authorize(): bool
    {
        abort_unless($this->salesOrder()->organization_id === $this->currentOrganization()->id, 404);

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => [
                'required',
                'integer',
                Rule::exists(Warehouse::class, 'id')
                    ->where('organization_id', $this->currentOrganization()->id)
                    ->where('is_active', true),
            ],
            'returned_at' => ['required', 'date', 'before_or_equal:+5 minutes'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.sales_order_line_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists(SalesOrderLine::class, 'id')->where('sales_order_id', $this->salesOrder()->id),
            ],
            'lines.*.quantity' => ['nullable', 'integer', 'min:0', 'max:10000'],
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
            'lines.*.sales_order_line_id.exists' => __('This line is not on the order.'),
        ];
    }

    /**
     * Check no line takes back more than was sent and not yet returned, something comes back at all, and the
     * serials were dispatched on that line.
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

                $orderLines = $this->salesOrder()->lines()->get()->keyBy('id');
                $totalUnits = 0;
                $serialGroups = [];

                foreach ($this->input('lines') as $index => $line) {
                    $orderLine = $orderLines[(int) $line['sales_order_line_id']];
                    $quantity = (int) $line['quantity'];
                    $totalUnits += $quantity;

                    if ($quantity > $orderLine->quantityReturnable()) {
                        $validator->errors()->add("lines.{$index}.quantity", trans_choice(
                            'Only :count unit was sent and not yet returned.|Only :count units were sent and not yet returned.',
                            $orderLine->quantityReturnable(),
                        ));
                    }

                    $serialGroups["lines.{$index}.serials"] = [
                        'quantity' => $quantity,
                        'serials' => $line['serials'] ?? [],
                        'product_variant_id' => $orderLine->product_variant_id,
                        'sales_order_line_id' => $orderLine->id,
                    ];
                }

                if ($totalUnits === 0) {
                    $validator->errors()->add('lines', __('Enter a quantity for at least one line.'));
                }

                $this->validateReturnedSerials($validator, $this->currentOrganization(), $serialGroups);
            },
        ];
    }

    /**
     * Treat a blank quantity as 0 and split each line's serials text into a list.
     */
    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines');

        if (! is_array($lines)) {
            return;
        }

        $this->merge([
            'lines' => array_map(function (mixed $line): mixed {
                if (! is_array($line)) {
                    return $line;
                }

                if (in_array($line['quantity'] ?? null, [null, ''], true)) {
                    $line['quantity'] = 0;
                }

                $line['serials'] = is_string($line['serials'] ?? null)
                    ? array_values(array_filter(
                        array_map('trim', preg_split('/\r\n|\r|\n/', $line['serials'])),
                        fn (string $serial): bool => $serial !== '',
                    ))
                    : ($line['serials'] ?? []);

                return $line;
            }, $lines),
        ]);
    }

    public function salesOrder(): SalesOrder
    {
        return $this->route('salesOrder');
    }

    private function currentOrganization(): Organization
    {
        return $this->attributes->get('organization');
    }
}
