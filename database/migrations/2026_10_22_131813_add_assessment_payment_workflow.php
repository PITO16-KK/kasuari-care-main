<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table): void {
            $table->foreignId('sale_id')->nullable()->unique()->constrained()->nullOnDelete();
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->foreignId('cashier_id')->nullable()->change();
            $table->timestamp('paid_at')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('sales')->whereNull('cashier_id')->orWhereNull('paid_at')->exists()) {
            throw new RuntimeException('Complete or remove pending assessment invoices before reverting this migration.');
        }

        Schema::table('sales', function (Blueprint $table): void {
            $table->foreignId('cashier_id')->nullable(false)->change();
            $table->timestamp('paid_at')->nullable(false)->change();
        });

        Schema::table('assessments', function (Blueprint $table): void {
            $table->dropForeign(['sale_id']);
            $table->dropUnique(['sale_id']);
            $table->dropColumn('sale_id');
        });
    }
};
