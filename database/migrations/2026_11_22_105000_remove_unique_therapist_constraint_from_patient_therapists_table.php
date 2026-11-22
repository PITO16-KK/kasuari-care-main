<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('patient_therapists', function (Blueprint $table) {
            $table->dropUnique('patient_therapists_therapist_profile_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_therapists', function (Blueprint $table) {
            $table->unique('therapist_profile_id', 'patient_therapists_therapist_profile_id_unique');
        });
    }
};
