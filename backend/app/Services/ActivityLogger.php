<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ActivityLogger
{
    public function log(
        string $action,
        ?Model $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $companyId = null,
        ?int $userId = null,
        ?Request $request = null
    ): ActivityLog {
        $request = $request ?: request();

        return ActivityLog::create([
            'company_id' => $companyId ?: $this->resolveCompanyId(),
            'user_id' => $userId ?: auth()->id(),
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    private function resolveCompanyId(): ?int
    {
        if (app()->bound('company.id')) {
            return app('company.id');
        }

        return auth()->user()?->current_company_id;
    }
}