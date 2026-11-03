<?php

namespace Database\Factories;

use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TherapistProfile>
 */
class TherapistProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'license_number' => fake()->bothify('STR-####'),
            'specialty' => fake()->randomElement(['Terapi Wicara', 'Terapi Okupasi', 'Fisioterapi']),
        ];
    }
}
