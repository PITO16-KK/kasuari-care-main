<?php

namespace Database\Seeders;

use App\Models\PatientIntakeSubmission;
use Illuminate\Database\Seeder;

class PatientIntakeSubmissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isProduction() || PatientIntakeSubmission::query()->exists()) {
            return;
        }

        PatientIntakeSubmission::factory()->create();
    }
}
