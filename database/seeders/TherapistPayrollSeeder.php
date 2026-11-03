<?php

namespace Database\Seeders;

use App\Models\TherapistPayroll;
use App\Models\TherapistProfile;
use Illuminate\Database\Seeder;

class TherapistPayrollSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isProduction() || TherapistPayroll::query()->exists()) {
            return;
        }

        $therapist = TherapistProfile::query()->first() ?? TherapistProfile::factory()->create();

        TherapistPayroll::factory()->count(3)->create(['therapist_profile_id' => $therapist->id]);
    }
}
