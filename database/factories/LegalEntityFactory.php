<?php

namespace Database\Factories;

use App\Models\LegalEntity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegalEntity>
 */
class LegalEntityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'legal_name' => fake()->company(),
            'trade_name' => fake()->company(),
            'cnpj' => fake()->unique()->numerify('##############'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('(11) 9####-####'),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => 'SP',
            'zip_code' => fake()->numerify('########'),
            'country' => 'Brasil',
            'is_active' => true,
        ];
    }
}
