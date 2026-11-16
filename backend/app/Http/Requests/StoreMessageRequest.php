<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMessageRequest extends FormRequest
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

        return [
            'provider_id' => [
                'nullable',
                'integer',
                Rule::exists('delivery_providers', 'id')
                    ->where('company_id', $companyId)
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],

            'template_id' => [
                'nullable',
                'integer',
                Rule::exists('message_templates', 'id')
                    ->where(function ($query) use ($companyId) {
                        $query->where('company_id', $companyId)
                            ->orWhereNull('company_id');
                    })
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],

            'channel' => ['required', Rule::in(['email'])],

            'subject' => ['nullable', 'required_without:template_id', 'string', 'max:255'],
            'body_html' => ['nullable', 'string'],
            'body_text' => ['nullable', 'string'],

            'variables' => ['nullable', 'array'],

            'scheduled_at' => ['nullable', 'date'],

            'allow_fallback' => ['nullable', 'boolean'],

            'contact_ids' => ['nullable', 'array'],
            'contact_ids.*' => [
                'integer',
                Rule::exists('contacts', 'id')
                    ->where('company_id', $companyId)
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],

            'contact_list_ids' => ['nullable', 'array'],
            'contact_list_ids.*' => [
                'integer',
                Rule::exists('contact_lists', 'id')
                    ->where('company_id', $companyId)
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],

            'recipients' => ['nullable', 'array'],
            'recipients.*.name' => ['nullable', 'string', 'max:255'],
            'recipients.*.email' => ['nullable', 'email', 'max:255'],
            'recipients.*.phone' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $hasContacts = ! empty($this->input('contact_ids', []));
            $hasLists = ! empty($this->input('contact_list_ids', []));
            $hasManualRecipients = ! empty($this->input('recipients', []));

            if (! $hasContacts && ! $hasLists && ! $hasManualRecipients) {
                $validator->errors()->add(
                    'recipients',
                    'Informe ao menos um contato, lista ou destinatário manual.'
                );
            }

            if (! $this->filled('template_id')) {
                $hasBody = $this->filled('body_html') || $this->filled('body_text');

                if (! $hasBody) {
                    $validator->errors()->add(
                        'body',
                        'Informe o corpo da mensagem ou selecione um template.'
                    );
                }
            }
        });
    }
}
