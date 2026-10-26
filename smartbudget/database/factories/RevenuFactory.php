<?php

namespace Database\Factories;

use App\Models\Categorie;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Revenu>
 */
class RevenuFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'categorie_id' => fn (array $attrs) => Categorie::factory()->revenu()->create(['user_id' => $attrs['user_id']])->id,
            'montant' => fake()->numberBetween(5000, 500000),
            'date' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'description' => fake()->sentence(3),
        ];
    }
}
