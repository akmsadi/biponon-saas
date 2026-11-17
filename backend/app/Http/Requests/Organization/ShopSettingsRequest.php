<?php

namespace App\Http\Requests\Organization;

use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates the online shop switch. The shop can only open while a default warehouse is set,
 * because web orders ship from it.
 */
class ShopSettingsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'shop_enabled' => ['boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Organization $organization */
                $organization = $this->attributes->get('organization');

                if ($this->boolean('shop_enabled') && $organization->defaultWarehouseId() === null) {
                    $validator->errors()->add('shop_enabled', __('Set an active default warehouse under Inventory defaults first. Web orders ship from it.'));
                }
            },
        ];
    }
}
