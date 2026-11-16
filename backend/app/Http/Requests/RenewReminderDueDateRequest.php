<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RenewReminderDueDateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = app('company.id');

        return [
            'new_due_date' => ['required', 'date'],
            'label' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],

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

            'copy_alert_rules' => ['nullable', 'boolean'],
            'copy_recipients' => ['nullable', 'boolean'],
        ];
    }
}
