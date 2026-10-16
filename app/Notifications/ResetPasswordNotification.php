<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Correu de restabliment de contrasenya en català, amb enllaç a la SPA.
 */
class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = url('/restableix-contrasenya').'?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->subject('Restabliment de la contrasenya')
            ->greeting('Hola!')
            ->line('Has rebut aquest correu perquè s\'ha demanat restablir la contrasenya del teu compte.')
            ->action('Restableix la contrasenya', $url)
            ->line("Aquest enllaç caduca d'aquí a {$minutes} minuts.")
            ->line('Si no ho has demanat tu, no cal que facis res.')
            ->salutation('GestPartits');
    }
}
