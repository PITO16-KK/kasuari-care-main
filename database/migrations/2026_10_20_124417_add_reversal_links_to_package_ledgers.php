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
        Schema::table('package_ledger', function (Blueprint $table): void {
            $table->foreignId('reversal_of_id')->nullable()->constrained('package_ledger')->restrictOnDelete();
            $table->unique('reversal_of_id');
        });

        Schema::table('duration_package_ledger', function (Blueprint $table): void {
            $table->foreignId('reversal_of_id')->nullable()->constrained('duration_package_ledger')->restrictOnDelete();
            $table->unique('reversal_of_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('duration_package_ledger', function (Blueprint $table): void {
            $table->dropForeign(['reversal_of_id']);
            $table->dropUnique(['reversal_of_id']);
            $table->dropColumn('reversal_of_id');
        });

        Schema::table('package_ledger', function (Blueprint $table): void {
            $table->dropForeign(['reversal_of_id']);
            $table->dropUnique(['reversal_of_id']);
            $table->dropColumn('reversal_of_id');
        });
    }
};
