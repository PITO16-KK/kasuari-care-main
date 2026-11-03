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
        Schema::table('session_reports', function (Blueprint $table) {
            $table->timestamp('edited_at')->nullable()->after('submitted_at');
            $table->foreignId('edited_by_user_id')->nullable()->after('edited_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('session_reports', function (Blueprint $table) {
            $table->dropForeign(['edited_by_user_id']);
            $table->dropColumn(['edited_at', 'edited_by_user_id']);
        });
    }
};
