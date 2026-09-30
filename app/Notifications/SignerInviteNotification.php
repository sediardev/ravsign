<?php

namespace App\Notifications;

use App\Models\Signer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SignerInviteNotification extends Notification implements ShouldQueue
{
    public function __construct(private readonly Signer $signer) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $document = $this->signer->document;
        $sender = $document->user;

        $url = route('sign.show', ['token' => $this->signer->token]);

        return (new MailMessage)
            ->subject("You were invited to sign \"{$document->name}\"")
            ->greeting("Hello, {$this->signer->name}")
            ->line("{$sender->name} invited you to sign the document \"{$document->name}\" on Ravsign.")
            ->action('Confirm and sign', $url)
            ->line('This link is personal and non-transferable: do not share it with anyone else.');
    }
}
