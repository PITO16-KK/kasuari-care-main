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
        Schema::table('package_catalog', function (Blueprint $table): void {
            $table->unsignedSmallInteger('session_duration_minutes')->nullable()->after('price');
            $table->unsignedSmallInteger('session_quantity')->nullable()->after('session_duration_minutes');
        });

        Schema::table('service_catalog', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('price');
        });

        Schema::create('duration_package_entitlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_package_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedInteger('granted_sessions');
            $table->unsignedInteger('remaining_sessions');
            $table->unique(['patient_package_id', 'duration_minutes']);
        });

        Schema::create('duration_package_ledger', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('duration_package_entitlement_id')->constrained()->restrictOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('quantity_delta');
            $table->string('reason', 40);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['appointment_id', 'duration_package_entitlement_id'], 'duration_ledger_appointment_entitlement_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('duration_package_ledger');
        Schema::dropIfExists('duration_package_entitlements');

        Schema::table('service_catalog', function (Blueprint $table): void {
            $table->dropColumn('description');
        });

        Schema::table('package_catalog', function (Blueprint $table): void {
            $table->dropColumn(['session_duration_minutes', 'session_quantity']);
        });
    }
};
