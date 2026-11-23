<?php

use Illuminate\Http\Request;
use App\Http\Controllers\Api\ApiClientController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ContactListController;
use App\Http\Controllers\Api\DeliveryProviderController;
use App\Http\Controllers\Api\IntegrationMessageController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\MessageScheduleController;
use App\Http\Controllers\Api\MessageTemplateController;
use App\Http\Controllers\Api\PublicReminderActionController;
use App\Http\Controllers\Api\ReminderDueDateController;
use App\Http\Controllers\Api\ReminderItemController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ManagedCompanyController;
use App\Http\Controllers\Api\CompanyTeamController;
use App\Http\Controllers\Api\TeamImportController;
use App\Http\Controllers\Api\AsoOperationsController;
use App\Models\Employee;
use App\Models\EmploymentLink;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'company.context'])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::get('/health', function () {
            return response()->json([
                'message' => 'X Flow API online',
                'company_id' => app('company.id'),
            ]);
        });

        Route::apiResource('contacts', ContactController::class);
        Route::apiResource('contact-lists', ContactListController::class);

        Route::apiResource('delivery-providers', DeliveryProviderController::class);
        Route::post(
            '/delivery-providers/{delivery_provider}/test',
            [DeliveryProviderController::class, 'test']
        );

        Route::apiResource('message-templates', MessageTemplateController::class);

        Route::post(
            '/message-templates/{message_template}/preview',
            [MessageTemplateController::class, 'preview']
        );

        Route::apiResource('messages', MessageController::class)
            ->only(['index', 'store', 'show', 'destroy']);

        Route::apiResource('reminder-items', ReminderItemController::class);

        Route::post(
            '/reminder-due-dates/{reminder_due_date}/resolve',
            [ReminderDueDateController::class, 'resolve']
        );

        Route::post(
            '/reminder-due-dates/{reminder_due_date}/renew',
            [ReminderDueDateController::class, 'renew']
        );

        Route::apiResource('api-clients', ApiClientController::class);

        Route::post(
            '/api-clients/{api_client}/regenerate',
            [ApiClientController::class, 'regenerate']
        );

        Route::apiResource('message-schedules', MessageScheduleController::class);

        Route::apiResource('managed-companies', ManagedCompanyController::class);

        Route::get('/team-members', [CompanyTeamController::class, 'indexFlat']);
        Route::post('/team-members', [CompanyTeamController::class, 'storeFlat']);
        Route::post('/team-members/{employee}/links', [CompanyTeamController::class, 'addLinkFlat']);
        Route::patch('/employment-links/{employmentLink}', [CompanyTeamController::class, 'updateLinkFlat']);
        Route::put('/employment-links/{employmentLink}', [CompanyTeamController::class, 'updateLinkFlat']);

        Route::post('/managed-companies/{managedCompany}/team-imports', [TeamImportController::class, 'upload']);
        Route::post('/managed-companies/{managedCompany}/team-imports/{teamImportBatch}/preview', [TeamImportController::class, 'preview']);
        Route::post('/managed-companies/{managedCompany}/team-imports/{teamImportBatch}/commit', [TeamImportController::class, 'commit']);

        Route::get('/aso-monitoring', [AsoOperationsController::class, 'report']);
        Route::get('/managed-companies/{managedCompany}/employment-links/{employmentLink}/asos', [AsoOperationsController::class, 'index']);
        Route::post('/managed-companies/{managedCompany}/employment-links/{employmentLink}/asos', [AsoOperationsController::class, 'store']);
        Route::post(
            '/managed-companies/{managed_company}/team-imports/{team_import_batch}/corrections',
            [TeamImportController::class, 'corrections']
        );

    });

    // resolve por e-mail
    Route::post(
        '/public/reminder-due-dates/resolve-token',
        [PublicReminderActionController::class, 'resolveByToken']
    );

    Route::middleware(['api.client'])->group(function () {
        Route::post('/integrations/messages', [IntegrationMessageController::class, 'store']);

        Route::get('/integrations/messages/{message}', [IntegrationMessageController::class, 'show']);

        Route::get(
            '/integrations/messages/status/by-idempotency-key',
            [IntegrationMessageController::class, 'statusByIdempotencyKey']
        );
    });

});
