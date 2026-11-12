<?php

namespace App\Notifications;

use App\Models\Location;
use App\Notifications\Concerns\DecritLocation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NouvelleDemandeLocationNotification extends Notification
{
    use DecritLocation, Queueable;

    public function __construct(private readonly Location $location) {}

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

        return $this->donnees(
            $location,
            'nouvelle_demande',
            'Nouvelle demande de location',
            $location->locataire->name.' souhaite louer '.$this->objet($location).' '.$this->periode($location).'.',
            '/proprietaire/demandes?statut='.Location::STATUT_EN_ATTENTE,
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $location = $this->location;

        $mail = (new MailMessage)
            ->subject('Nouvelle demande de location pour « '.$location->materiel->nom.' »')
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line($location->locataire->name.' souhaite louer '.$this->objet($location).' '.$this->periode($location).'.')
            ->line(
                ($location->quantite > 1 ? $location->quantite.' exemplaires, ' : '')
                .$location->nb_jours.' jour'.($location->nb_jours > 1 ? 's' : '').' · '.$this->montant($location->prix_total),
            );

        if (filled($location->message)) {
            $mail->line('Message du locataire : '.$location->message);
        }

        return $mail
            ->action('Consulter la demande', $this->lien('/proprietaire/demandes?statut='.Location::STATUT_EN_ATTENTE))
            ->line('Confirmez ou refusez cette demande depuis votre espace propriétaire.');
    }

    private function objet(Location $location): string
    {
        $nom = '« '.$location->materiel->nom.' »';

        return $location->quantite > 1 ? $location->quantite.' × '.$nom : $nom;
    }
}
