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
        Schema::create('patient_therapists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('therapy_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('therapist_profile_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['patient_id', 'therapy_type_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_therapists');
    }
};
