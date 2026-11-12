<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    public const CODE_EXPIRATION_MINUTES = 10;

    use Queueable;

    public function __construct(private readonly string $token) {}

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
            ->subject('Code de vérification LocaMat')
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Vous recevez cet e-mail car une réinitialisation du mot de passe de votre compte a été demandée.')
            ->line('Votre code de vérification est :')
            ->line($this->token)
            ->line('Ce code expire dans '.self::CODE_EXPIRATION_MINUTES.' minutes.')
            ->line('Si vous n’êtes pas à l’origine de cette demande, ignorez cet e-mail.');
    }
}
