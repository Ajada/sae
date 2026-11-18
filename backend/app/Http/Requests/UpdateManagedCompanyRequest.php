<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateManagedCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('cnpj') && is_string($this->input('cnpj'))) {
            $this->merge(['cnpj' => preg_replace('/\D/', '', $this->input('cnpj'))]);
        }
    }

    public function rules(): array
    {
        $record = $this->route('managed_company');
        return [
            'corporate_name' => ['sometimes', 'required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'cnpj' => [
                'sometimes', 'required', 'digits:14',
                Rule::unique('managed_companies', 'cnpj')
                    ->where('company_id', app('company.id'))
                    ->ignore($record?->id),
                function ($attribute, $value, $fail) {
                    if (! StoreManagedCompanyRequest::validCnpj((string) $value)) {
                        $fail('O CNPJ informado é inválido.');
                    }
                },
            ],
            'risk_grade' => ['sometimes', 'required', 'integer', 'between:1,4'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
