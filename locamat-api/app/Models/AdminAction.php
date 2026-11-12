<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Journal d'activité de la plateforme : actions d'administration et activité des utilisateurs
 * (inscription, connexion, matériels, locations…). Consultable uniquement par le super-administrateur.
 */
#[Fillable(['user_id', 'acteur_nom', 'acteur_role', 'action', 'cible_type', 'cible_id', 'cible_libelle', 'concerne_user_id', 'details'])]
class AdminAction extends Model
{
    public const UPDATED_AT = null;

    // Administration
    public const STATUT_MODIFIE = 'statut_modifie';

    public const COMPTE_DEBLOQUE = 'compte_debloque';

    public const PROFIL_MODIFIE = 'profil_modifie';

    public const COMPTE_SUPPRIME = 'compte_supprime';

    public const COMPTE_RESTAURE = 'compte_restaure';

    public const COMPTE_PURGE = 'compte_purge';

    public const ADMIN_CREE = 'admin_cree';

    public const MATERIEL_DISPONIBILITE = 'materiel_disponibilite';

    public const MATERIEL_SUPPRIME = 'materiel_supprime';

    public const MATERIEL_RESTAURE = 'materiel_restaure';

    public const MATERIEL_PURGE = 'materiel_purge';

    public const CATEGORIE_CREEE = 'categorie_creee';

    public const CATEGORIE_MODIFIEE = 'categorie_modifiee';

    public const CATEGORIE_SUPPRIMEE = 'categorie_supprimee';

    public const CATEGORIE_RESTAUREE = 'categorie_restauree';

    public const CATEGORIE_PURGEE = 'categorie_purgee';

    // Compte et sécurité
    public const INSCRIPTION = 'inscription';

    public const CONNEXION = 'connexion';

    public const DECONNEXION = 'deconnexion';

    public const COMPTE_BLOQUE = 'compte_bloque';

    public const PROFIL_PERSONNEL_MODIFIE = 'profil_personnel_modifie';

    public const MOT_DE_PASSE_MODIFIE = 'mot_de_passe_modifie';

    public const MOT_DE_PASSE_REINITIALISE = 'mot_de_passe_reinitialise';

    // Matériels (côté propriétaire)
    public const MATERIEL_CREE = 'materiel_cree';

    public const MATERIEL_MODIFIE = 'materiel_modifie';

    // Locations
    public const LOCATION_DEMANDEE = 'location_demandee';

    public const LOCATION_CONFIRMEE = 'location_confirmee';

    public const LOCATION_REFUSEE = 'location_refusee';

    public const LOCATION_ANNULEE = 'location_annulee';

    public const LOCATION_TERMINEE = 'location_terminee';

    public const PROLONGATION_DEMANDEE = 'prolongation_demandee';

    public const PROLONGATION_CONFIRMEE = 'prolongation_confirmee';

    public const PROLONGATION_REFUSEE = 'prolongation_refusee';

    public const RECLAMATION_CREEE = 'reclamation_creee';

    public const RECLAMATION_MISE_A_JOUR = 'reclamation_mise_a_jour';

    public const CIBLE_UTILISATEUR = 'utilisateur';

    public const CIBLE_MATERIEL = 'materiel';

    public const CIBLE_CATEGORIE = 'categorie';

    public const CIBLE_LOCATION = 'location';

    public const CIBLE_RECLAMATION = 'reclamation';

    /**
     * Correspondance entre l'historique d'une location et le journal de la plateforme.
     *
     * @var array<string, string>
     */
    public const ACTIONS_LOCATION = [
        LocationHistorique::CREATION => self::LOCATION_DEMANDEE,
        LocationHistorique::CONFIRMATION => self::LOCATION_CONFIRMEE,
        LocationHistorique::REFUS => self::LOCATION_REFUSEE,
        LocationHistorique::ANNULATION => self::LOCATION_ANNULEE,
        LocationHistorique::TERMINEE => self::LOCATION_TERMINEE,
        LocationHistorique::PROLONGATION_DEMANDEE => self::PROLONGATION_DEMANDEE,
        LocationHistorique::PROLONGATION_ACCEPTEE => self::PROLONGATION_CONFIRMEE,
        LocationHistorique::PROLONGATION_REFUSEE => self::PROLONGATION_REFUSEE,
    ];

    /**
     * @return list<string>
     */
    public static function actions(): array
    {
        return [
            self::STATUT_MODIFIE,
            self::COMPTE_DEBLOQUE,
            self::PROFIL_MODIFIE,
            self::COMPTE_SUPPRIME,
            self::COMPTE_RESTAURE,
            self::COMPTE_PURGE,
            self::ADMIN_CREE,
            self::MATERIEL_DISPONIBILITE,
            self::MATERIEL_SUPPRIME,
            self::MATERIEL_RESTAURE,
            self::MATERIEL_PURGE,
            self::CATEGORIE_CREEE,
            self::CATEGORIE_MODIFIEE,
            self::CATEGORIE_SUPPRIMEE,
            self::CATEGORIE_RESTAUREE,
            self::CATEGORIE_PURGEE,
            self::INSCRIPTION,
            self::CONNEXION,
            self::DECONNEXION,
            self::COMPTE_BLOQUE,
            self::PROFIL_PERSONNEL_MODIFIE,
            self::MOT_DE_PASSE_MODIFIE,
            self::MOT_DE_PASSE_REINITIALISE,
            self::MATERIEL_CREE,
            self::MATERIEL_MODIFIE,
            self::LOCATION_DEMANDEE,
            self::LOCATION_CONFIRMEE,
            self::LOCATION_REFUSEE,
            self::LOCATION_ANNULEE,
            self::LOCATION_TERMINEE,
            self::PROLONGATION_DEMANDEE,
            self::PROLONGATION_CONFIRMEE,
            self::PROLONGATION_REFUSEE,
            self::RECLAMATION_CREEE,
            self::RECLAMATION_MISE_A_JOUR,
            // Ancienne entrée du journal (changement de rôle, fonction supprimée), conservée pour l'historique.
            'role_modifie',
        ];
    }

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function acteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
}
