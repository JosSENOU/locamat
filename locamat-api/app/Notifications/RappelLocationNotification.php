<?php

namespace App\Notifications;

use App\Models\Location;
use App\Notifications\Concerns\DecritLocation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RappelLocationNotification extends Notification
{
    use DecritLocation, Queueable;

    public const TYPE_REMISE = 'remise';

    public const TYPE_RETOUR = 'retour';

    public const TYPE_RETARD = 'retard';

    public const TYPE_CONFIRMATION = 'confirmation';

    /**
     * @param  self::TYPE_*  $type
     */
    public function __construct(
        private readonly Location $location,
        private readonly string $type,
        private readonly bool $pourLeProprietaire,
        private readonly int $joursRestants = 0,
        private readonly bool $avecMail = true,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->avecMail ? ['database', 'mail'] : ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $location = $this->location;
        $lignes = $this->lignes($location, $location->materiel->nom);

        return ['echeance' => $this->echeance()] + $this->donnees(
            $location,
            'rappel_'.$this->type,
            match ($this->type) {
                self::TYPE_REMISE => 'Rappel de remise du matériel',
                self::TYPE_RETOUR => $this->veilleDuRetour() ? ($this->joursRestants === 0 ? 'Date limite de retour aujourd’hui' : 'Date limite de retour demain') : 'Rappel de retour du matériel',
                self::TYPE_CONFIRMATION => 'Le matériel a-t-il été rendu ?',
                default => 'Retour en retard',
            },
            $lignes[0],
            $this->pourLeProprietaire ? '/proprietaire/demandes?statut='.Location::STATUT_CONFIRMEE : '/mes-locations',
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $location = $this->location;
        $nom = $location->materiel->nom;

        $mail = (new MailMessage)
            ->subject($this->sujet($nom))
            ->greeting('Bonjour '.$notifiable->name.',');

        foreach ($this->lignes($location, $nom) as $ligne) {
            $mail->line($ligne);
        }

        return $mail->action(
            $this->pourLeProprietaire ? 'Ouvrir mes demandes' : 'Voir mes locations',
            $this->lien($this->pourLeProprietaire ? '/proprietaire/demandes?statut='.Location::STATUT_CONFIRMEE : '/mes-locations'),
        );
    }

    /**
     * Rappel de la veille adressé au locataire : le matériel n'est pas encore rendu et l'échéance est demain.
     */
    private function veilleDuRetour(): bool
    {
        return $this->type === self::TYPE_RETOUR && ! $this->pourLeProprietaire && $this->joursRestants <= 1;
    }

    /**
     * Distingue le rappel de la veille du rappel « standard » : chacun n'est créé qu'une seule fois par location.
     */
    public function echeance(): string
    {
        return $this->veilleDuRetour() ? 'veille' : 'standard';
    }

    private function sujet(string $nom): string
    {
        return match ($this->type) {
            self::TYPE_REMISE => 'Rappel : remise du matériel « '.$nom.' »',
            self::TYPE_RETOUR => $this->veilleDuRetour()
                ? 'Dernier jour '.($this->joursRestants === 0 ? 'aujourd’hui' : 'demain').' : rendez « '.$nom.' »'
                : 'Rappel : retour du matériel « '.$nom.' »',
            self::TYPE_CONFIRMATION => 'Retour à confirmer : « '.$nom.' » a-t-il été rendu ?',
            default => 'Retour en retard : « '.$nom.' »',
        };
    }

    /**
     * @return list<string>
     */
    private function lignes(Location $location, string $nom): array
    {
        $echeance = match (true) {
            $this->joursRestants > 1 => 'dans '.$this->joursRestants.' jours',
            $this->joursRestants === 1 => 'demain',
            default => 'aujourd’hui',
        };
        $locataire = $location->locataire;
        $proprietaire = $location->materiel->proprietaire;

        if ($this->veilleDuRetour()) {
            return [
                'La date limite de retour de « '.$nom.' » est '.($this->joursRestants === 0 ? 'aujourd’hui' : 'demain').', le '.$location->date_fin->format('d/m/Y').' : le matériel n’est pas encore enregistré comme rendu.',
                'Déposez-le avant la fin de la journée '.($this->joursRestants === 0 ? 'd’aujourd’hui' : 'de demain').' chez le propriétaire'.$this->coordonnees($proprietaire).'. Si vous avez besoin de plus de temps, demandez une prolongation dès aujourd’hui. Après l’échéance, chaque jour de retard est facturé au tarif journalier majoré de 10 %.',
            ];
        }

        return match ([$this->type, $this->pourLeProprietaire]) {
            [self::TYPE_REMISE, false] => [
                'La remise du matériel « '.$nom.' » est prévue '.$echeance.', le '.$location->date_debut->format('d/m/Y').'.',
                'Contactez le propriétaire'.$this->coordonnees($proprietaire).' pour convenir du lieu et de l’heure.',
            ],
            [self::TYPE_REMISE, true] => [
                'Le matériel « '.$nom.' » doit être remis à '.$locataire->name.' '.$echeance.', le '.$location->date_debut->format('d/m/Y').'.',
                'Contactez le locataire'.$this->coordonnees($locataire).' pour convenir du lieu et de l’heure.',
            ],
            [self::TYPE_RETOUR, false] => [
                'Le matériel « '.$nom.' » doit être rendu '.$echeance.', le '.$location->date_fin->format('d/m/Y').'.',
                $this->joursRestants <= 1
                    ? 'Pensez à ramener le matériel au propriétaire dans les 24 heures'.$this->coordonnees($proprietaire).'. Après l’échéance, chaque jour de retard est facturé au tarif journalier majoré de 10 %.'
                    : 'Si vous avez besoin de plus de temps, demandez une prolongation avant cette date. Après l’échéance, chaque jour de retard est facturé au tarif journalier majoré de 10 %.',
            ],
            [self::TYPE_RETOUR, true] => [
                'Le matériel « '.$nom.' » doit vous être rendu par '.$locataire->name.' '.$echeance.', le '.$location->date_fin->format('d/m/Y').'.',
                'Une fois le matériel récupéré, vérifiez-le et confirmez le retour depuis votre espace.',
            ],
            [self::TYPE_RETARD, false] => [
                'La date de retour de « '.$nom.' » (le '.$location->date_fin->format('d/m/Y').') est dépassée.',
                'Rendez le matériel dès que possible : chaque jour de retard est facturé au tarif journalier majoré de 10 %.',
            ],
            [self::TYPE_CONFIRMATION, true] => [
                'La date limite de retour de « '.$nom.' » par '.$locataire->name.' était le '.$location->date_fin->format('d/m/Y').'.',
                'Vous pouvez maintenant vérifier le matériel et confirmer sa réception depuis vos locations confirmées. Sinon, contactez le locataire'.$this->coordonnees($locataire).'.',
            ],
            default => [
                $locataire->name.' n’a pas encore rendu « '.$nom.' » (retour prévu le '.$location->date_fin->format('d/m/Y').').',
                'Dès que le matériel est récupéré, vérifiez-le et confirmez le retour : la pénalité de retard sera calculée à ce moment.',
            ],
        };
    }

    private function coordonnees(?object $personne): string
    {
        return filled($personne?->telephone) ? ' ('.$personne->telephone.')' : '';
    }
}
