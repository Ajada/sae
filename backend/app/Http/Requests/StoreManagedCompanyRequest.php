<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManagedCompanyRequest extends FormRequest
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
        return [
            'corporate_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'cnpj' => [
                'required', 'digits:14',
                Rule::unique('managed_companies', 'cnpj')->where('company_id', app('company.id')),
                function ($attribute, $value, $fail) {
                    if (! self::validCnpj((string) $value)) {
                        $fail('O CNPJ informado é inválido.');
                    }
                },
            ],
            'risk_grade' => ['required', 'integer', 'between:1,4'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public static function validCnpj(string $cnpj): bool
    {
        if (! preg_match('/^\d{14}$/', $cnpj) || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }
        foreach ([12, 13] as $length) {
            $sum = 0;
            $weight = $length === 12 ? 5 : 6;
            for ($i = 0; $i < $length; $i++) {
                $sum += (int) $cnpj[$i] * $weight;
                $weight = $weight === 2 ? 9 : $weight - 1;
            }
            $mod = $sum % 11;
            $check = $mod < 2 ? 0 : 11 - $mod;
            if ((int) $cnpj[$length] !== $check) return false;
        }
        return true;
    }
}
