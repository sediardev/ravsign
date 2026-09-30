<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends Notification implements ShouldQueue
{
    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );

        return (new MailMessage)
            ->subject('Confirma tu correo en Ravsign')
            ->greeting('¡Hola!')
            ->line('Gracias por registrarte en Ravsign. Confirma tu dirección de correo para empezar a firmar documentos.')
            ->action('Confirmar correo', $url)
            ->line('Si no creaste una cuenta en Ravsign, puedes ignorar este correo.');
    }
}
