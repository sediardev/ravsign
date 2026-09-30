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
            ->subject('Confirm your email on Ravsign')
            ->greeting('Hello!')
            ->line('Thanks for signing up for Ravsign. Confirm your email address to start signing documents.')
            ->action('Confirm email', $url)
            ->line('If you did not create an account on Ravsign, you can ignore this email.');
    }
}
