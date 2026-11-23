<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMessageScheduleRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],

            'channel' => ['required', Rule::in(['email'])],

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

            'subject' => ['nullable', 'required_without:template_id', 'string', 'max:255'],
            'body_html' => ['nullable', 'string'],
            'body_text' => ['nullable', 'string'],

            'variables' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],

            'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly', 'yearly'])],

            'day_of_week' => ['nullable', 'integer', 'min:1', 'max:7'],
            'day_of_month' => ['nullable', 'integer', 'min:1', 'max:31'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],

            'send_time' => ['required', 'date_format:H:i'],
            'timezone' => ['nullable', 'string', 'max:100'],

            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],

            'status' => ['nullable', Rule::in(['active', 'paused', 'inactive'])],
            'allow_fallback' => ['nullable', 'boolean'],

            'recipients' => ['required', 'array', 'min:1'],
            'recipients.*.type' => ['required', Rule::in(['contact', 'contact_list', 'manual'])],

            'recipients.*.contact_id' => [
                'nullable',
                'integer',
                Rule::exists('contacts', 'id')
                    ->where('company_id', $companyId)
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],

            'recipients.*.contact_list_id' => [
                'nullable',
                'integer',
                Rule::exists('contact_lists', 'id')
                    ->where('company_id', $companyId)
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],

            'recipients.*.name' => ['nullable', 'string', 'max:255'],
            'recipients.*.email' => ['nullable', 'email', 'max:255'],
            'recipients.*.phone' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $frequency = $this->input('frequency');

            if ($frequency === 'weekly' && ! $this->filled('day_of_week')) {
                $validator->errors()->add('day_of_week', 'Informe o dia da semana.');
            }

            if (in_array($frequency, ['monthly', 'yearly'], true) && ! $this->filled('day_of_month')) {
                $validator->errors()->add('day_of_month', 'Informe o dia do mês.');
            }

            if ($frequency === 'yearly' && ! $this->filled('month')) {
                $validator->errors()->add('month', 'Informe o mês.');
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

            foreach ($this->input('recipients', []) as $index => $recipient) {
                $type = $recipient['type'] ?? null;

                if ($type === 'contact' && empty($recipient['contact_id'])) {
                    $validator->errors()->add("recipients.$index.contact_id", 'Informe o contato.');
                }

                if ($type === 'contact_list' && empty($recipient['contact_list_id'])) {
                    $validator->errors()->add("recipients.$index.contact_list_id", 'Informe a lista.');
                }

                if ($type === 'manual') {
                    $hasEmail = ! empty($recipient['email']);
                    $hasPhone = ! empty($recipient['phone']);

                    if (! $hasEmail && ! $hasPhone) {
                        $validator->errors()->add(
                            "recipients.$index.email",
                            'Informe e-mail ou telefone para o destinatário manual.'
                        );
                    }
                }
            }
        });
    }
}
