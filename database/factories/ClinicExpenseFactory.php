<?php

namespace Database\Factories;

use App\Models\ClinicExpense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClinicExpense>
 */
class ClinicExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Listrik', 'Perlengkapan terapi', 'Alat tulis kantor']),
            'amount' => fake()->numberBetween(10000, 5000000),
            'expense_date' => fake()->date(),
            'proof_path' => null,
            'created_by' => User::factory(),
        ];
    }
}
