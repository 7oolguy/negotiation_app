<?php

namespace Database\Factories;

use App\Models\LegalEntity;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<School>
 */
class SchoolFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'legal_entity_id' => LegalEntity::factory(),
            'name' => fake()->company(),
            'legal_name' => fake()->company(),
            'cnpj' => fake()->unique()->numerify('##############'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('(11) 9####-####'),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => 'SP',
            'zip_code' => fake()->numerify('########'),
            'country' => 'Brasil',
            'website' => fake()->url(),
            'status' => 'active',
        ];
    }
}
