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
            ->subject("Te invitaron a firmar «{$document->name}»")
            ->greeting("Hola, {$this->signer->name}")
            ->line("{$sender->name} te invitó a firmar el documento «{$document->name}» en Ravsign.")
            ->action('Confirmar y firmar', $url)
            ->line('Este enlace es personal e intransferible: no lo compartas con nadie más.');
    }
}
