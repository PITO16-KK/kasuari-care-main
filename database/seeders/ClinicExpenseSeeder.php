<?php

namespace Database\Seeders;

use App\Models\ClinicExpense;
use Illuminate\Database\Seeder;

class ClinicExpenseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isProduction() || ClinicExpense::query()->exists()) {
            return;
        }

        ClinicExpense::factory()->count(5)->create();
    }
}
