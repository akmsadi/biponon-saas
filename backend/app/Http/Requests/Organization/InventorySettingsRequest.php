<?php

namespace App\Http\Requests\Organization;

use App\Models\Organization;
use App\Models\Warehouse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the inventory defaults: an active warehouse of this organization and a reorder level.
 */
class InventorySettingsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Organization $organization */
        $organization = $this->attributes->get('organization');

        return [
            'default_warehouse_id' => [
                'nullable',
                'integer',
                Rule::exists(Warehouse::class, 'id')
                    ->where('organization_id', $organization->id)
                    ->where('is_active', true),
            ],
            'default_reorder_level' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    /**
     * The warehouse picker sends "none" for no default.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('default_warehouse_id') === 'none') {
            $this->merge(['default_warehouse_id' => null]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'default_warehouse_id' => __('default warehouse'),
            'default_reorder_level' => __('default reorder level'),
        ];
    }
}
