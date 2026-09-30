<?php

namespace App\Notifications;

use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification implements ShouldQueue
{
    public function __construct(public readonly string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(CanResetPassword $notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('Reset your Ravsign password')
            ->greeting('Hello!')
            ->line('We received a request to reset the password for your Ravsign account.')
            ->action('Reset password', $url)
            ->line('This link expires in 60 minutes.')
            ->line('If you did not request this change, you can ignore this email.');
    }
}
