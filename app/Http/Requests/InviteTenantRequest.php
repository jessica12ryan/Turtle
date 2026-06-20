<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InviteTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isLandlord() || $this->user()->isPropertyManager() || $this->user()->isTenant();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'property_id' => ['required', 'exists:properties,id'],
            'is_main_tenant' => ['sometimes', 'boolean'],
        ];
    }
}
