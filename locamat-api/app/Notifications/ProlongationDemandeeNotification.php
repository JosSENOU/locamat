<?php

namespace App\Notifications;

use App\Models\Location;
use App\Notifications\Concerns\DecritLocation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProlongationDemandeeNotification extends Notification
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
            'prolongation_demandee',
            'Demande de prolongation',
            $location->locataire->name.' demande de prolonger « '.$location->materiel->nom.' » jusqu’au '.$location->prolongation_date_fin->format('d/m/Y').'.',
            '/proprietaire/demandes?statut='.Location::STATUT_CONFIRMEE,
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $location = $this->location;

        return (new MailMessage)
            ->subject('Demande de prolongation pour « '.$location->materiel->nom.' »')
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line($location->locataire->name.' demande de prolonger la location de « '.$location->materiel->nom.' ».')
            ->line('Retour actuellement prévu le '.$location->date_fin->format('d/m/Y').', nouvelle date demandée : '.$location->prolongation_date_fin->format('d/m/Y').'.')
            ->line('Supplément : '.$this->montant($location->prolongation_montant).' (tarif journalier majoré de 10 %).')
            ->action('Répondre à la demande', $this->lien('/proprietaire/demandes?statut='.Location::STATUT_CONFIRMEE));
    }
}
