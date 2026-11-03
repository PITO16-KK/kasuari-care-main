<?php

namespace Database\Factories;

use App\Models\TherapistPayroll;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TherapistPayroll>
 */
class TherapistPayrollFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'therapist_profile_id' => TherapistProfile::factory(),
            'payroll_period' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-01'),
            'amount' => fake()->numberBetween(1000000, 15000000),
            'payment_date' => fake()->date(),
            'proof_path' => null,
            'created_by' => User::factory(),
        ];
    }
}
