<?php

namespace App\Notifications;

use App\Models\Location;
use App\Notifications\Concerns\DecritLocation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProlongationTraiteeNotification extends Notification
{
    use DecritLocation, Queueable;

    public function __construct(
        private readonly Location $location,
        private readonly bool $acceptee,
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
            $this->acceptee ? 'prolongation_acceptee' : 'prolongation_refusee',
            'Prolongation '.($this->acceptee ? 'acceptée' : 'refusée'),
            $this->acceptee
                ? 'Votre location de « '.$nom.' » est prolongée jusqu’au '.$location->date_fin->format('d/m/Y').'.'
                : 'La prolongation de « '.$nom.' » est refusée : rendez le matériel le '.$location->date_fin->format('d/m/Y').'.',
            '/mes-locations',
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $location = $this->location;
        $nom = $location->materiel->nom;

        $mail = (new MailMessage)
            ->subject('Prolongation '.($this->acceptee ? 'acceptée' : 'refusée').' pour « '.$nom.' »')
            ->greeting('Bonjour '.$notifiable->name.',');

        if ($this->acceptee) {
            $mail
                ->line('Le propriétaire a accepté la prolongation de votre location de « '.$nom.' ».')
                ->line('Nouvelle date de retour : '.$location->date_fin->format('d/m/Y').'.')
                ->line('Montant total de la location : '.$this->montant($location->prix_total).'.');
        } else {
            $mail
                ->line('Le propriétaire a refusé la prolongation de votre location de « '.$nom.' ».')
                ->line('Merci de rendre le matériel le '.$location->date_fin->format('d/m/Y').' pour éviter une pénalité de retard.');
        }

        return $mail->action('Voir mes locations', $this->lien('/mes-locations'));
    }
}
