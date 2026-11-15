<?php

namespace App\Http\Requests\Catalog;

use App\Models\Color;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ColorRequest extends FormRequest
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

        /** @var Color|null $color */
        $color = $this->route('color');

        return [
            'name' => [
                'required',
                'string',
                'max:60',
                Rule::unique(Color::class)
                    ->where('organization_id', $organization->id)
                    ->ignore($color?->id),
            ],
            'code' => ['nullable', 'string', 'max:30'],
            'hex_code' => ['nullable', 'hex_color', 'size:7'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Normalize the hex code to upper case before validation.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('hex_code'))) {
            $this->merge(['hex_code' => strtoupper($this->input('hex_code'))]);
        }
    }
}
