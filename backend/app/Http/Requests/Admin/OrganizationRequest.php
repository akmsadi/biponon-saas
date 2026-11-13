<?php

namespace App\Http\Requests\Admin;

use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Validates an organization created or edited by a super-admin. Creating also needs the first admin's email;
 * editing also sets the status. A blank slug is derived from the name.
 */
class OrganizationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Organization|null $organization */
        $organization = $this->route('organization');

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique(Organization::class)->ignore($organization?->id),
            ],
        ];

        if ($organization === null) {
            $rules['admin_email'] = ['required', 'string', 'email', 'max:255'];
        } else {
            $rules['status'] = ['required', 'string', Rule::in(Organization::STATUSES)];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => __('The slug may only contain lower-case letters, numbers and single hyphens.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'admin_email' => __('admin email'),
        ];
    }

    /**
     * Lower-case the slug and admin email; derive a missing slug from the name.
     */
    protected function prepareForValidation(): void
    {
        $slug = is_string($this->input('slug')) ? trim($this->input('slug')) : '';

        if ($slug === '' && is_string($this->input('name'))) {
            $slug = Str::slug($this->input('name'));
        }

        $this->merge(['slug' => mb_strtolower($slug)]);

        if (is_string($this->input('admin_email'))) {
            $this->merge(['admin_email' => mb_strtolower(trim($this->input('admin_email')))]);
        }
    }
}
