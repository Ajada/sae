<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeliveryProviderRequest extends FormRequest
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

            'channel' => ['sometimes', 'required', Rule::in(['email'])],
            'driver' => ['sometimes', 'required', Rule::in(['smtp'])],

            'host' => ['sometimes', 'required', 'string', 'max:255'],
            'port' => ['sometimes', 'required', 'integer', 'min:1', 'max:65535'],

            'username' => ['nullable', 'string', 'max:255'],

            // Só atualiza a senha se esse campo vier preenchido.
            'password' => ['nullable', 'string', 'max:255'],

            'encryption' => ['nullable', Rule::in(['tls', 'ssl'])],

            'from_name' => ['sometimes', 'required', 'string', 'max:255'],
            'from_email' => ['sometimes', 'required', 'email', 'max:255'],
            'reply_to_email' => ['nullable', 'email', 'max:255'],

            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:999'],
        ];
    }
}
