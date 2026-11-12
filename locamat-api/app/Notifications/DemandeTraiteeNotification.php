<?php

namespace App\Notifications;

use App\Models\Location;
use App\Notifications\Concerns\DecritLocation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DemandeTraiteeNotification extends Notification
{
    use DecritLocation, Queueable;

    public function __construct(
        private readonly Location $location,
        private readonly bool $confirmee,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $location = $this->location;
        $nom = $location->materiel->nom;

        return $this->donnees(
            $location,
            $this->confirmee ? 'demande_confirmee' : 'demande_refusee',
            'Demande '.($this->confirmee ? 'confirmée' : 'refusée'),
            $this->confirmee
                ? 'Votre demande de location de « '.$nom.' » '.$this->periode($location).' est confirmée.'
                : 'Votre demande de location de « '.$nom.' » '.$this->periode($location).' a été refusée.',
            '/mes-locations',
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $location = $this->location;
        $nom = $location->materiel->nom;

        $mail = (new MailMessage)
            ->subject('Demande '.($this->confirmee ? 'confirmée' : 'refusée').' pour « '.$nom.' »')
            ->greeting('Bonjour '.$notifiable->name.',');

        if ($this->confirmee) {
            $mail
                ->line('Le propriétaire a confirmé votre demande de location de « '.$nom.' » '.$this->periode($location).'.')
                ->line('Montant total : '.$this->montant($location->prix_total).'.');
        } else {
            $mail->line('Le propriétaire a refusé votre demande de location de « '.$nom.' » '.$this->periode($location).'.');

            if (filled($location->motif_refus)) {
                $mail->line('Motif : '.$location->motif_refus);
            }
        }

        return $mail->action('Voir mes locations', $this->lien('/mes-locations'));
    }
}
