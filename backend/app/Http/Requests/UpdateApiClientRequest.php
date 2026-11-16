<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateApiClientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'allowed_ips' => ['nullable', 'array'],
            'allowed_ips.*' => ['string', 'max:255'],
            'allowed_origins' => ['nullable', 'array'],
            'allowed_origins.*' => ['string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
