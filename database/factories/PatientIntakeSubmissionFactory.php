<?php

namespace Database\Factories;

use App\Models\PatientIntakeSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientIntakeSubmission>
 */
class PatientIntakeSubmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => 'google_form',
            'response_id' => fake()->uuid(),
            'submitted_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'child_data' => null,
            'father_data' => null,
            'mother_data' => null,
            'family_context' => null,
            'development_history' => null,
            'health_history' => null,
            'verification_status' => 'pending',
            'patient_id' => null,
        ];
    }
}
