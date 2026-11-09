<?php

namespace App\Http\Requests\Catalog;

use App\Models\Organization;
use App\Models\Size;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SizeRequest extends FormRequest
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

        /** @var Size|null $size */
        $size = $this->route('size');

        return [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique(Size::class)
                    ->where('organization_id', $organization->id)
                    ->ignore($size?->id),
            ],
            'code' => ['nullable', 'string', 'max:30'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['boolean'],
        ];
    }
}
