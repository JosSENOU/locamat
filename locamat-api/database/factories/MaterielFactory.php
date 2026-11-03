<?php

namespace Database\Factories;

use App\Models\Materiel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Materiel>
 */
class MaterielFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'proprietaire_id' => User::factory()->proprietaire(),
            'nom' => fake()->words(3, true),
            'categorie' => fake()->word(),
            'description' => fake()->sentence(),
            'photo' => null,
            'prix_journalier' => fake()->randomFloat(2, 1, 200),
            'etat' => fake()->randomElement(['neuf', 'bon', 'usé']),
            'disponible' => true,
        ];
    }
}
