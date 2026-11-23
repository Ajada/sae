<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContactListRequest extends FormRequest
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
        $companyId = app('company.id');
        $list = $this->route('contact_list');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('contact_lists', 'name')
                    ->where('company_id', $companyId)
                    ->whereNull('deleted_at')
                    ->ignore($list?->id),
            ],

            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],

            'contact_ids' => ['nullable', 'array'],
            'contact_ids.*' => [
                'integer',
                Rule::exists('contacts', 'id')
                    ->where('company_id', $companyId)
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
