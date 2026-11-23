<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMessageScheduleRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'channel' => ['sometimes', 'required', Rule::in(['email'])],

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

            'subject' => ['nullable', 'string', 'max:255'],
            'body_html' => ['nullable', 'string'],
            'body_text' => ['nullable', 'string'],

            'variables' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],

            'frequency' => ['sometimes', 'required', Rule::in(['daily', 'weekly', 'monthly', 'yearly'])],

            'day_of_week' => ['nullable', 'integer', 'min:1', 'max:7'],
            'day_of_month' => ['nullable', 'integer', 'min:1', 'max:31'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],

            'send_time' => ['sometimes', 'required', 'date_format:H:i'],
            'timezone' => ['nullable', 'string', 'max:100'],

            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],

            'status' => ['nullable', Rule::in(['active', 'paused', 'inactive', 'completed', 'cancelled'])],
            'allow_fallback' => ['nullable', 'boolean'],

            'recipients' => ['nullable', 'array', 'min:1'],
            'recipients.*.type' => ['required_with:recipients', Rule::in(['contact', 'contact_list', 'manual'])],
            'recipients.*.contact_id' => ['nullable', 'integer'],
            'recipients.*.contact_list_id' => ['nullable', 'integer'],
            'recipients.*.name' => ['nullable', 'string', 'max:255'],
            'recipients.*.email' => ['nullable', 'email', 'max:255'],
            'recipients.*.phone' => ['nullable', 'string', 'max:30'],
        ];
    }
}
