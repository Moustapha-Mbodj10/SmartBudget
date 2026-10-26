<?php

namespace Database\Factories;

use App\Models\Categorie;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Depense>
 */
class DepenseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'categorie_id' => fn (array $attrs) => Categorie::factory()->create(['user_id' => $attrs['user_id']])->id,
            'montant' => fake()->numberBetween(500, 100000),
            'date' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'description' => fake()->sentence(3),
        ];
    }
}
