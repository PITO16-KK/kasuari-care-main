<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('therapy_types')->where('code', 'TO')->update([
                'code' => 'OT',
                'name' => 'Terapi Okupasi',
                'updated_at' => now(),
            ]);

            DB::table('therapy_types')->where('code', 'OT')->update([
                'name' => 'Terapi Okupasi',
                'is_active' => true,
                'updated_at' => now(),
            ]);

            $physiotherapyType = DB::table('therapy_types')->where('code', 'FT');

            if ($physiotherapyType->exists()) {
                $physiotherapyType->update([
                    'name' => 'Fisioterapi',
                    'is_active' => true,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('therapy_types')->insert([
                    'code' => 'FT',
                    'name' => 'Fisioterapi',
                    'default_duration_minutes' => 60,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $inactiveTypeIds = DB::table('therapy_types')
                ->whereIn('code', ['SI', 'ABA'])
                ->pluck('id');

            DB::table('therapy_types')->whereIn('id', $inactiveTypeIds)->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

            DB::table('service_catalog')
                ->whereIn('therapy_type_id', $inactiveTypeIds)
                ->where('service_type', 'session')
                ->update(['is_active' => false, 'updated_at' => now()]);

            DB::table('service_catalog')->where('code', 'SESI-TO')->update([
                'code' => 'SESI-OT',
                'name' => 'Sesi 1 Jam - Terapi Okupasi',
                'updated_at' => now(),
            ]);

            DB::table('rooms')->where('code', 'TO')->update([
                'code' => 'OT',
                'name' => 'Ruang Okupasi',
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::transaction(function (): void {
            DB::table('therapy_types')->where('code', 'OT')->update([
                'code' => 'TO',
                'name' => 'Terapi Okupasi',
                'updated_at' => now(),
            ]);

            DB::table('therapy_types')->whereIn('code', ['SI', 'ABA'])->update([
                'is_active' => true,
                'updated_at' => now(),
            ]);

            $restoredTypeIds = DB::table('therapy_types')
                ->whereIn('code', ['SI', 'ABA'])
                ->pluck('id');

            DB::table('service_catalog')
                ->whereIn('therapy_type_id', $restoredTypeIds)
                ->where('service_type', 'session')
                ->update(['is_active' => true, 'updated_at' => now()]);

            DB::table('service_catalog')->where('code', 'SESI-OT')->update([
                'code' => 'SESI-TO',
                'name' => 'Sesi 1 Jam - Terapi Okupasi',
                'updated_at' => now(),
            ]);

            DB::table('rooms')->where('code', 'OT')->update([
                'code' => 'TO',
                'name' => 'Ruang Okupasi',
                'updated_at' => now(),
            ]);

        });
    }
};
