<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SiteModeUpdateRequest extends FormRequest
{
    /**
     * Only super-admins reach this request; the route's `can:super-admin` middleware enforces it.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'demo_mode' => ['required', 'boolean'],
        ];
    }
}
