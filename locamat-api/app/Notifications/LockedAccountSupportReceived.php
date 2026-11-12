<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class LockedAccountSupportReceived extends Notification
{
    use Queueable;

    public function __construct(private readonly Carbon $lockedUntil) {}

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
            ->subject('Votre demande a été envoyée à LocaMat')
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Votre message concernant le blocage de votre compte a bien été transmis à l’administrateur.')
            ->line('Votre compte sera rétabli au plus tard le '.$this->lockedUntil->timezone(config('app.timezone'))->format('d/m/Y à H:i').'.')
            ->line('Vous pourrez également vous reconnecter plus tôt si l’administrateur débloque votre compte.');
    }
}
