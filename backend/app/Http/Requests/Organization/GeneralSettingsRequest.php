<?php

namespace App\Http\Requests\Organization;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the organization's name, contact details and timezone.
 */
class GeneralSettingsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
        ];
    }

    /**
     * Trim the text fields and lower-case the email.
     */
    protected function prepareForValidation(): void
    {
        foreach (['name', 'contact_email', 'phone', 'address'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }

        if (is_string($this->input('contact_email'))) {
            $this->merge(['contact_email' => mb_strtolower($this->input('contact_email'))]);
        }
    }
}
