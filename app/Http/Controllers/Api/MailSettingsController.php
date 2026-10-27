<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Communications\MailSettingsRequest;
use App\Services\AuditLogger;
use App\Support\MailSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Configuració del correu (SMTP): només l'administrador la pot veure i canviar.
 */
class MailSettingsController extends Controller
{
    public function __construct(private readonly MailSettings $settings) {}

    public function show(): JsonResponse
    {
        $this->authorize('manage-settings');

        return $this->state();
    }

    public function update(MailSettingsRequest $request, AuditLogger $audit): JsonResponse
    {
        $this->settings->save($request->validated(), $request->user()->id);

        // La contrasenya no es registra mai.
        $audit->log('settings.mail_updated', null, null, collect($request->validated())->except('password')->all());

        return $this->state();
    }

    /**
     * Envia un correu de prova perquè l'administrador comprovi la configuració desada.
     */
    public function test(Request $request): JsonResponse
    {
        $this->authorize('manage-settings');
        $data = $request->validate(['email' => ['nullable', 'email', 'max:255']]);
        $to = $data['email'] ?? $request->user()->email;

        try {
            Mail::raw(__('messages.mail_test_body'), fn ($message) => $message->to($to)->subject(__('messages.mail_test_subject')));
        } catch (Throwable $e) {
            return response()->json(['message' => __('messages.mail_test_failed', ['error' => $e->getMessage()])], 422);
        }

        return response()->json(['data' => ['sent_to' => $to]]);
    }

    private function state(): JsonResponse
    {
        return response()->json(['data' => $this->settings->values()]);
    }
}
