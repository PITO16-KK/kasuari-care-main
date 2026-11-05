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
        Schema::create('patient_intake_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('source', 100);
            $table->string('response_id')->unique();
            $table->timestamp('submitted_at')->nullable();
            $table->json('child_data')->nullable();
            $table->json('father_data')->nullable();
            $table->json('mother_data')->nullable();
            $table->json('family_context')->nullable();
            $table->json('development_history')->nullable();
            $table->json('health_history')->nullable();
            $table->string('verification_status', 30)->default('pending');
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['verification_status', 'submitted_at'], 'pis_verification_status_submitted_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_intake_submissions');
    }
};
