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
        Schema::create('therapist_payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('therapist_profile_id')->constrained()->restrictOnDelete();
            $table->date('payroll_period');
            $table->unsignedBigInteger('amount');
            $table->date('payment_date');
            $table->string('proof_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['payment_date', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('therapist_payrolls');
    }
};
