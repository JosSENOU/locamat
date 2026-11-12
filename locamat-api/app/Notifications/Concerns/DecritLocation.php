<?php

namespace App\Notifications\Concerns;

use App\Models\Location;

trait DecritLocation
{
    private function periode(Location $location): string
    {
        $debut = $location->date_debut->format('d/m/Y');
        $fin = $location->date_fin->format('d/m/Y');

        return $debut === $fin ? "le {$debut}" : "du {$debut} au {$fin}";
    }

    private function montant(string|float $montant): string
    {
        $valeur = (float) $montant;
        $decimales = abs($valeur - round($valeur)) < 0.005 ? 0 : 2;

        return number_format($valeur, $decimales, ',', ' ').' FCFA';
    }

    private function lien(string $chemin): string
    {
        return rtrim((string) config('app.frontend_url'), '/').$chemin;
    }

    /**
     * Contenu stocké pour la notification dans l'application.
     * `lien` est un chemin relatif du frontend.
     *
     * @return array{type: string, titre: string, message: string, lien: string, location_id: int, materiel_id: int}
     */
    private function donnees(Location $location, string $type, string $titre, string $message, string $lien): array
    {
        return [
            'type' => $type,
            'titre' => $titre,
            'message' => $message,
            'lien' => $lien,
            'location_id' => $location->id,
            'materiel_id' => $location->materiel_id,
        ];
    }
}
