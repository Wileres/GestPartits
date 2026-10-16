<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\MeResource;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    public function change(ChangePasswordRequest $request, AuditLogger $audit): MeResource
    {
        /** @var User $user */
        $user = $request->user();

        $user->forceFill([
            'password' => $request->string('password')->value(),
            'must_change_password' => false,
            'password_changed_at' => now(),
            'remember_token' => Str::random(60),
        ])->save();

        // Tanca les altres sessions de l'usuari i renova l'actual.
        DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();
        $request->session()->regenerate();

        $audit->log('auth.password_changed', $user);

        return new MeResource($user->load('role'));
    }

    /**
     * Resposta idèntica tant si el correu existeix com si no (evita l'enumeració d'usuaris).
     */
    public function forgot(ForgotPasswordRequest $request, AuditLogger $audit): JsonResponse
    {
        $status = Password::sendResetLink([
            'email' => $request->string('email')->lower()->value(),
            'is_active' => true,
        ]);

        $audit->log('auth.password_reset_requested', null, null, [
            'email' => $request->string('email')->lower()->value(),
            'status' => $status,
        ]);

        return response()->json(['message' => __('passwords.sent_generic')]);
    }

    public function reset(ResetPasswordRequest $request, AuditLogger $audit): JsonResponse
    {
        $status = Password::reset(
            [
                'email' => $request->string('email')->lower()->value(),
                'password' => $request->string('password')->value(),
                'password_confirmation' => $request->string('password_confirmation')->value(),
                'token' => $request->string('token')->value(),
                'is_active' => true,
            ],
            function (User $user, string $password) use ($audit): void {
                $user->forceFill([
                    'password' => $password,
                    'must_change_password' => false,
                    'password_changed_at' => now(),
                    'remember_token' => Str::random(60),
                ])->save();

                DB::table('sessions')->where('user_id', $user->id)->delete();

                $audit->log('auth.password_reset', $user, null, null, $user->id);

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return response()->json(['message' => __($status)]);
    }
}
