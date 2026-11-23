<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMessageTemplateRequest extends FormRequest
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
        $template = $this->route('message_template');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],

            'key' => [
                'nullable',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('message_templates', 'key')
                    ->where('company_id', $companyId)
                    ->whereNull('deleted_at')
                    ->ignore($template?->id),
            ],

            'channel' => ['sometimes', 'required', Rule::in(['email'])],

            'subject' => ['nullable', 'string', 'max:255'],

            'body_html' => ['nullable', 'string'],
            'body_text' => ['nullable', 'string'],

            'variables' => ['nullable', 'array'],
            'variables.*' => ['string', 'max:255'],

            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
