<?php

namespace App\Services;

use App\Models\AdminAction;
use App\Models\User;

class JournalAdministration
{
    /**
     * Garde une trace durable d'une action (administration ou activité d'un utilisateur). Le nom de
     * l'acteur est copié : l'historique reste lisible même si son compte est un jour purgé. Sans
     * acteur, l'action est attribuée au système (purge automatique, blocage de compte).
     *
     * `$concerneUserId` désigne l'autre utilisateur touché par l'action (le propriétaire d'une
     * location demandée par un locataire, par exemple) : l'événement apparaît dans son historique.
     *
     * @param  array<string, mixed>  $details
     */
    public function enregistrer(
        ?User $acteur,
        string $action,
        string $cibleType,
        int|string|null $cibleId,
        ?string $cibleLibelle = null,
        array $details = [],
        ?int $concerneUserId = null,
    ): AdminAction {
        return AdminAction::query()->create([
            'user_id' => $acteur?->getKey(),
            'acteur_nom' => $acteur?->name ?? 'Système',
            'acteur_role' => $acteur?->primaryRole(),
            'action' => $action,
            'cible_type' => $cibleType,
            'cible_id' => $cibleId,
            'cible_libelle' => $cibleLibelle,
            'concerne_user_id' => $concerneUserId,
            'details' => $details === [] ? null : $details,
        ]);
    }
}
