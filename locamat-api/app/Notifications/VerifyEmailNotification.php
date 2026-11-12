<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
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
            ->subject('Vérifiez votre adresse e-mail LocaMat')
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Confirmez votre adresse e-mail pour activer votre compte LocaMat.')
            ->action('Vérifier mon adresse e-mail', $url)
            ->line('Ce lien est valable pendant 60 minutes.');
    }
}
