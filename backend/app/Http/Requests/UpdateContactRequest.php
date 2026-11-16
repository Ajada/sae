<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateContactRequest extends FormRequest
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

            'email' => [
                'nullable',
                'required_without:phone',
                'email',
                'max:255',
            ],

            'phone' => [
                'nullable',
                'required_without:email',
                'string',
                'max:30',
            ],

            'company_name' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required_without' => 'Informe um e-mail ou telefone para o contato.',
            'phone.required_without' => 'Informe um telefone ou e-mail para o contato.',
        ];
    }
}
