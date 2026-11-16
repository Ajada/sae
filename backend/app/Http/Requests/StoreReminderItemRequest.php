<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReminderItemRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'issued_by' => ['nullable', 'string', 'max:255'],
            'reviewed_by' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],

            'due_dates' => ['required', 'array', 'min:1'],

            'due_dates.*.label' => ['nullable', 'string', 'max:255'],
            'due_dates.*.due_date' => ['required', 'date'],
            'due_dates.*.channel' => ['nullable', Rule::in(['email'])],
            'due_dates.*.notes' => ['nullable', 'string'],
            'due_dates.*.allow_fallback' => ['nullable', 'boolean'],

            'due_dates.*.provider_id' => [
                'nullable',
                'integer',
                Rule::exists('delivery_providers', 'id')
                    ->where('company_id', $companyId)
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],

            'due_dates.*.template_id' => [
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

            'due_dates.*.alert_rules' => ['required', 'array', 'min:1'],
            'due_dates.*.alert_rules.*.type' => [
                'required',
                Rule::in(['days_before', 'exact_date', 'on_due_date', 'after_due_repeat']),
            ],
            'due_dates.*.alert_rules.*.days_before' => ['nullable', 'integer', 'min:0'],
            'due_dates.*.alert_rules.*.exact_date' => ['nullable', 'date'],
            'due_dates.*.alert_rules.*.notify_on_due_date' => ['nullable', 'boolean'],
            'due_dates.*.alert_rules.*.repeat_after_due' => ['nullable', 'boolean'],
            'due_dates.*.alert_rules.*.repeat_interval_days' => ['nullable', 'integer', 'min:1'],
            'due_dates.*.alert_rules.*.repeat_until_resolved' => ['nullable', 'boolean'],
            'due_dates.*.alert_rules.*.expires_after_days' => ['nullable', 'integer', 'min:1'],
            'due_dates.*.alert_rules.*.is_active' => ['nullable', 'boolean'],

            'due_dates.*.recipients' => ['required', 'array', 'min:1'],
            'due_dates.*.recipients.*.type' => [
                'required',
                Rule::in(['contact', 'contact_list', 'manual']),
            ],

            'due_dates.*.recipients.*.contact_id' => [
                'nullable',
                'integer',
                Rule::exists('contacts', 'id')
                    ->where('company_id', $companyId)
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],

            'due_dates.*.recipients.*.contact_list_id' => [
                'nullable',
                'integer',
                Rule::exists('contact_lists', 'id')
                    ->where('company_id', $companyId)
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],

            'due_dates.*.recipients.*.name' => ['nullable', 'string', 'max:255'],
            'due_dates.*.recipients.*.email' => ['nullable', 'email', 'max:255'],
            'due_dates.*.recipients.*.phone' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ($this->input('due_dates', []) as $dueDateIndex => $dueDate) {
                foreach ($dueDate['alert_rules'] ?? [] as $ruleIndex => $rule) {
                    $type = $rule['type'] ?? null;

                    if ($type === 'days_before' && ! isset($rule['days_before'])) {
                        $validator->errors()->add(
                            "due_dates.$dueDateIndex.alert_rules.$ruleIndex.days_before",
                            'Informe a quantidade de dias antes do vencimento.'
                        );
                    }

                    if ($type === 'exact_date' && empty($rule['exact_date'])) {
                        $validator->errors()->add(
                            "due_dates.$dueDateIndex.alert_rules.$ruleIndex.exact_date",
                            'Informe a data exata do aviso.'
                        );
                    }

                    if ($type === 'after_due_repeat' && empty($rule['repeat_interval_days'])) {
                        $validator->errors()->add(
                            "due_dates.$dueDateIndex.alert_rules.$ruleIndex.repeat_interval_days",
                            'Informe o intervalo de repetição após o vencimento.'
                        );
                    }
                }

                foreach ($dueDate['recipients'] ?? [] as $recipientIndex => $recipient) {
                    $type = $recipient['type'] ?? null;

                    if ($type === 'contact' && empty($recipient['contact_id'])) {
                        $validator->errors()->add(
                            "due_dates.$dueDateIndex.recipients.$recipientIndex.contact_id",
                            'Informe o contato.'
                        );
                    }

                    if ($type === 'contact_list' && empty($recipient['contact_list_id'])) {
                        $validator->errors()->add(
                            "due_dates.$dueDateIndex.recipients.$recipientIndex.contact_list_id",
                            'Informe a lista de contatos.'
                        );
                    }

                    if ($type === 'manual') {
                        $hasEmail = ! empty($recipient['email']);
                        $hasPhone = ! empty($recipient['phone']);

                        if (! $hasEmail && ! $hasPhone) {
                            $validator->errors()->add(
                                "due_dates.$dueDateIndex.recipients.$recipientIndex.email",
                                'Informe e-mail ou telefone para o destinatário manual.'
                            );
                        }
                    }
                }
            }
        });
    }
}
