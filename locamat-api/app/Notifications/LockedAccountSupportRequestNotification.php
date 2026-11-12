<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LockedAccountSupportRequestNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $userName,
        private readonly string $userEmail,
        private readonly string $message,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Demande d’aide : compte LocaMat bloqué')
            ->greeting('Demande d’assistance')
            ->line('Compte : '.$this->userName)
            ->line('Adresse e-mail : '.$this->userEmail)
            ->line('Message de l’utilisateur :')
            ->line($this->message)
            ->line('Le compte sera automatiquement débloqué au plus tard 12 heures après son blocage.');
    }
}
