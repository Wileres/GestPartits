<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AvailabilitySlotController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\FieldBlockController;
use App\Http\Controllers\Api\ImageController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\MatchdayController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PasswordController;
use App\Http\Controllers\Api\ReferenceDataController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\SchedulingRuleController;
use App\Http\Controllers\Api\SeasonController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WhatsAppDestinationController;
use App\Http\Controllers\Api\WhatsAppTemplateController;
use Illuminate\Support\Facades\Route;

/*
| API REST de GestPartits (prefix /api). La SPA obté la cookie CSRF a /sanctum/csrf-cookie.
*/

Route::prefix('auth')->name('auth.')->group(function (): void {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login');
    Route::post('password/forgot', [PasswordController::class, 'forgot'])->middleware('throttle:password-email')->name('password.forgot');
    Route::post('password/reset', [PasswordController::class, 'reset'])->middleware('throttle:password-email')->name('password.reset');
});

Route::middleware(['auth:sanctum', 'active', 'password.changed'])->group(function (): void {
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('password/change', [PasswordController::class, 'change'])->name('password.change');
    });

    Route::apiResource('users', UserController::class)->except(['show']);
    Route::get('audit-logs', AuditLogController::class)->name('audit-logs.index');

    Route::get('reference-data', ReferenceDataController::class)->name('reference-data');

    // Categories i equips
    Route::apiResource('categories', CategoryController::class)->except(['show']);
    Route::post('categories/durations-reviewed', [CategoryController::class, 'markDurationsReviewed'])->name('categories.durations-reviewed');
    Route::put('teams/order', [TeamController::class, 'reorder'])->name('teams.order');
    Route::apiResource('teams', TeamController::class)->except(['show']);
    Route::get('teams/{team}/logo', [TeamController::class, 'logo'])->name('teams.logo');
    Route::post('teams/{team}/logo', [TeamController::class, 'uploadLogo'])->name('teams.logo.upload');
    Route::delete('teams/{team}/logo', [TeamController::class, 'deleteLogo'])->name('teams.logo.delete');

    // Temporades i caps de setmana
    Route::apiResource('seasons', SeasonController::class)->except(['show']);
    Route::get('seasons/{season}/matchdays', [MatchdayController::class, 'index'])->name('matchdays.index');
    Route::post('seasons/{season}/matchdays', [MatchdayController::class, 'store'])->name('matchdays.store');
    Route::apiResource('matchdays', MatchdayController::class)->only(['show', 'update', 'destroy']);

    // Partits
    Route::get('matchdays/{matchday}/matches', [MatchController::class, 'index'])->name('matches.index');
    Route::post('matchdays/{matchday}/matches', [MatchController::class, 'store'])->name('matches.store');
    Route::post('matchdays/{matchday}/matches/bulk', [MatchController::class, 'storeMany'])->name('matches.bulk');
    Route::get('matchdays/{matchday}/home-away-suggestions', [MatchController::class, 'homeAwaySuggestions'])->name('matches.suggestions');
    Route::apiResource('matches', MatchController::class)->only(['show', 'update', 'destroy']);

    // Programació de la jornada
    Route::get('matchdays/{matchday}/schedule', [ScheduleController::class, 'show'])->name('schedules.show');
    Route::prefix('schedules/{schedule}')->name('schedules.')->group(function (): void {
        Route::post('proposal', [ScheduleController::class, 'propose'])->middleware('throttle:sensitive')->name('propose');
        Route::post('save', [ScheduleController::class, 'save'])->name('save');
        Route::post('undo', [ScheduleController::class, 'undo'])->name('undo');
        Route::post('confirm', [ScheduleController::class, 'confirm'])->name('confirm');
        Route::post('moves/validate', [ScheduleController::class, 'validateMove'])->name('moves.validate');
        Route::post('moves', [ScheduleController::class, 'move'])->name('moves');
        Route::post('matches/{match}/lock', [ScheduleController::class, 'lock'])->name('lock');
        Route::delete('matches/{match}/lock', [ScheduleController::class, 'unlock'])->name('unlock');
        Route::get('versions/{from}/compare/{to}', [ScheduleController::class, 'compare'])->name('compare');

        // Imatges i avisos (només amb la programació confirmada)
        Route::get('images', [ImageController::class, 'index'])->name('images.index');
        Route::post('images', [ImageController::class, 'generate'])->middleware('throttle:sensitive')->name('images.generate');
        Route::get('whatsapp-messages', [NotificationController::class, 'index'])->name('whatsapp.index');
        Route::post('whatsapp/preview', [NotificationController::class, 'preview'])->name('whatsapp.preview');
        Route::post('whatsapp/send', [NotificationController::class, 'send'])->middleware('throttle:sensitive')->name('whatsapp.send');
        Route::post('whatsapp/retry-failed', [NotificationController::class, 'retryFailed'])->middleware('throttle:sensitive')->name('whatsapp.retry');
    });
    Route::get('generated-images/{generatedImage}', [ImageController::class, 'download'])->name('generated-images.download');
    Route::put('whatsapp-messages/{whatsappMessage}', [NotificationController::class, 'update'])->name('whatsapp-messages.update');
    Route::post('whatsapp-messages/{whatsappMessage}/mark-sent', [NotificationController::class, 'markSent'])->name('whatsapp-messages.mark-sent');
    Route::post('whatsapp-messages/{whatsappMessage}/discard', [NotificationController::class, 'discard'])->name('whatsapp-messages.discard');

    // Configuració de WhatsApp i aparença
    Route::get('whatsapp-templates/variables', [WhatsAppTemplateController::class, 'variables'])->name('whatsapp-templates.variables');
    Route::apiResource('whatsapp-templates', WhatsAppTemplateController::class)->except(['show'])->parameters(['whatsapp-templates' => 'whatsapp_template']);
    Route::get('whatsapp-destinations/providers', [WhatsAppDestinationController::class, 'providers'])->name('whatsapp-destinations.providers');
    Route::apiResource('whatsapp-destinations', WhatsAppDestinationController::class)->except(['show'])->parameters(['whatsapp-destinations' => 'whatsapp_destination']);
    Route::get('settings', [SettingsController::class, 'show'])->name('settings.show');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('settings/logo', [SettingsController::class, 'logo'])->name('settings.logo');
    Route::post('settings/logo', [SettingsController::class, 'uploadLogo'])->name('settings.logo.upload');
    Route::delete('settings/logo', [SettingsController::class, 'deleteLogo'])->name('settings.logo.delete');

    // Franges, bloquejos i regles
    Route::apiResource('availability-slots', AvailabilitySlotController::class)->except(['show']);
    Route::apiResource('field-blocks', FieldBlockController::class)->except(['show']);
    Route::apiResource('scheduling-rules', SchedulingRuleController::class)->except(['show']);
});
