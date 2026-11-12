<?php

namespace Database\Factories;

use App\Models\Category;
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
            'user_id' => User::factory()->proprietaire(),
            'nom' => fake()->words(3, true),
            'categorie_id' => Category::factory(),
            'description' => fake()->sentence(),
            'prix_journalier' => fake()->randomFloat(2, 1, 200),
            'etat' => fake()->randomElement(['neuf', 'bon', 'usé']),
            'disponible' => true,
        ];
    }
}
