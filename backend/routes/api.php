<?php

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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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
