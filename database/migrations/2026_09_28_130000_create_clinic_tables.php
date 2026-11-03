<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
        });

        Schema::create('parents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
        });

        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('patient_code', 20)->unique();
            $table->string('name');
            $table->date('birth_date')->nullable();
            $table->string('gender', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('primary_whatsapp', 30)->nullable();
            $table->text('main_complaint')->nullable();
            $table->string('school_name')->nullable();
            $table->text('medical_history')->nullable();
            $table->json('milestones')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['is_active', 'created_at']);
        });

        Schema::create('patient_code_counters', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
        });

        Schema::create('patient_parents', function (Blueprint $table) {
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->constrained()->cascadeOnDelete();
            $table->string('relation', 20);
            $table->boolean('is_primary_contact')->default(false);
            $table->boolean('can_access_portal')->default(false);
            $table->primary(['patient_id', 'parent_id']);
        });

        Schema::create('public_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('child_name');
            $table->date('birth_date')->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('parent_name');
            $table->string('parent_relation', 20)->default('guardian');
            $table->string('whatsapp', 30);
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->text('complaint')->nullable();
            $table->string('status', 20)->default('new')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->text('follow_up_notes')->nullable();
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('therapy_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8)->unique();
            $table->string('name');
            $table->unsignedSmallInteger('default_duration_minutes')->default(60);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('therapist_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('license_number')->nullable();
            $table->string('specialty')->nullable();
            $table->timestamps();
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('scheduled')->index();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('chief_complaint')->nullable();
            $table->json('development_history')->nullable();
            $table->json('learning_behavior')->nullable();
            $table->text('summary')->nullable();
            $table->text('referral_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('assessment_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('therapy_type_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('sessions_per_week')->default(1);
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('therapy_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('therapist_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assessment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('status', 20)->default('scheduled')->index();
            $table->string('booking_source', 20)->default('admin');
            $table->string('attendance_status', 20)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_note')->nullable();
            $table->timestamps();
            $table->index(['starts_at', 'status']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
            DB::statement("ALTER TABLE appointments ADD CONSTRAINT appointments_valid_time CHECK (ends_at > starts_at)");
            DB::statement("ALTER TABLE appointments ADD CONSTRAINT no_therapist_double_booking EXCLUDE USING gist (therapist_profile_id WITH =, tstzrange(starts_at, ends_at, '[)') WITH &&) WHERE (therapist_profile_id IS NOT NULL AND status <> 'cancelled')");
            DB::statement("ALTER TABLE appointments ADD CONSTRAINT no_room_double_booking EXCLUDE USING gist (room_id WITH =, tstzrange(starts_at, ends_at, '[)') WITH &&) WHERE (room_id IS NOT NULL AND status <> 'cancelled')");
        }

        Schema::create('iep_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('therapy_type_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('measurement_unit', 40)->nullable();
            $table->decimal('baseline', 8, 2)->nullable();
            $table->decimal('target_value', 8, 2)->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('session_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('therapist_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('draft');
            $table->text('activities')->nullable();
            $table->text('parent_notes')->nullable();
            $table->text('homework')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('session_goal_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('iep_goal_id')->constrained()->restrictOnDelete();
            $table->decimal('score', 8, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['session_report_id', 'iep_goal_id']);
        });

        Schema::create('package_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('validity_days')->default(90);
            $table->unsignedInteger('price');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('package_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_catalog_id')->constrained('package_catalog')->cascadeOnDelete();
            $table->foreignId('therapy_type_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('session_quantity');
            $table->unique(['package_catalog_id', 'therapy_type_id']);
        });

        Schema::create('service_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('service_type', 20);
            $table->foreignId('therapy_type_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('price');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->unsignedInteger('price');
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 32)->unique();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('paid');
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('discount_total')->default(0);
            $table->unsignedInteger('grand_total');
            $table->timestamp('paid_at');
            $table->timestamps();
        });

        Schema::create('patient_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_catalog_id')->constrained('package_catalog')->restrictOnDelete();
            $table->foreignId('sale_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamp('starts_at');
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index(['patient_id', 'status']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE UNIQUE INDEX one_active_package_per_patient ON patient_packages (patient_id) WHERE status = 'active'");
        }

        Schema::create('package_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('therapy_type_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('granted_sessions');
            $table->unsignedSmallInteger('remaining_sessions');
            $table->unique(['patient_package_id', 'therapy_type_id']);
        });

        Schema::create('package_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_entitlement_id')->constrained()->restrictOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('quantity_delta');
            $table->string('reason', 40);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['appointment_id', 'package_entitlement_id']);
        });

        Schema::create('sale_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->string('line_type', 20);
            $table->foreignId('package_catalog_id')->nullable()->constrained('package_catalog')->nullOnDelete();
            $table->foreignId('service_catalog_id')->nullable()->constrained('service_catalog')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedInteger('unit_price');
            $table->unsignedInteger('line_total');
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('method', 20);
            $table->unsignedInteger('amount');
            $table->string('reference_number')->nullable();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('received_at');
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('sale_lines');
        Schema::dropIfExists('package_ledger');
        Schema::dropIfExists('package_entitlements');
        Schema::dropIfExists('patient_packages');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('products');
        Schema::dropIfExists('service_catalog');
        Schema::dropIfExists('package_catalog_items');
        Schema::dropIfExists('package_catalog');
        Schema::dropIfExists('session_goal_scores');
        Schema::dropIfExists('session_reports');
        Schema::dropIfExists('iep_goals');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('assessment_recommendations');
        Schema::dropIfExists('assessments');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('therapist_profiles');
        Schema::dropIfExists('therapy_types');
        Schema::dropIfExists('public_registrations');
        Schema::dropIfExists('patient_parents');
        Schema::dropIfExists('patient_code_counters');
        Schema::dropIfExists('patients');
        Schema::dropIfExists('parents');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'is_active']);
        });
    }
};