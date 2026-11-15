<?php

namespace App\Http\Requests\Sales;

use App\Models\Customer;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
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

        /** @var Customer|null $customer */
        $customer = $this->route('customer');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Customer::class)
                    ->where('organization_id', $organization->id)
                    ->ignore($customer?->id),
            ],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Trim the text fields and lower-case the email.
     */
    protected function prepareForValidation(): void
    {
        foreach (['name', 'contact_name', 'email', 'phone', 'address', 'notes'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }

        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower($this->input('email'))]);
        }
    }
}
