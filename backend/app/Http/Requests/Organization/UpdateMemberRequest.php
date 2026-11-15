<?php

namespace App\Http\Requests\Organization;

use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a change to a member's role and status (active or suspended).
 */
class UpdateMemberRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::in(Organization::ROLES)],
            'status' => ['required', 'string', Rule::in(['active', 'suspended'])],
        ];
    }
}
