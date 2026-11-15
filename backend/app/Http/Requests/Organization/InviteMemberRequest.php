<?php

namespace App\Http\Requests\Organization;

use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates an invitation: an email address that is not a member yet (active or suspended) and a role.
 */
class InviteMemberRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', 'string', Rule::in(Organization::ROLES)],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('email')) {
                    return;
                }

                $isMember = $this->currentOrganization()->users()->where('email', $this->input('email'))->exists();

                if ($isMember) {
                    $validator->errors()->add('email', __('This person is already a member of the organization.'));
                }
            },
        ];
    }

    /**
     * Compare and store emails in lower case.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    private function currentOrganization(): Organization
    {
        return $this->attributes->get('organization');
    }
}
