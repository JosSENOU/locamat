<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Materiel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'materiel_id' => Materiel::factory(),
            'locataire_id' => User::factory(),
            'date_debut' => today()->addDay(),
            'date_fin' => today()->addDays(2),
            'nb_jours' => 2,
            'prix_total' => 100,
            'statut' => Location::STATUT_EN_ATTENTE,
            'message' => null,
            'motif_refus' => null,
        ];
    }
}
