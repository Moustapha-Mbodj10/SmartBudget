<?php

namespace Database\Factories;

use App\Models\Categorie;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Categorie>
 */
class CategorieFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'nom' => fake()->unique()->words(2, true),
            'description' => null,
            'type' => Categorie::TYPE_DEPENSE,
        ];
    }

    public function revenu(): static
    {
        return $this->state(fn () => ['type' => Categorie::TYPE_REVENU]);
    }
}
