<?php

namespace Database\Seeders;

use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['owner', 'front_office', 'therapist', 'finance', 'parent'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $owner = User::firstOrCreate(['email' => 'owner@rumahterapi.test'], [
            'name' => 'Pimpinan Klinik',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $owner->syncRoles(['owner']);

        $admin = User::firstOrCreate(['email' => 'admin@rumahterapi.test'], [
            'name' => 'Admin Front Office',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $admin->syncRoles(['front_office']);

        $cashier = User::firstOrCreate(['email' => 'kasir@rumahterapi.test'], [
            'name' => 'Kasir Klinik',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $cashier->syncRoles(['finance']);

        $therapist = User::firstOrCreate(['email' => 'terapis@rumahterapi.test'], [
            'name' => 'Terapis Demo',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $therapist->syncRoles(['therapist']);
        TherapistProfile::firstOrCreate(['user_id' => $therapist->id], ['specialty' => 'Terapi Anak']);

        $types = [
            ['TW', 'Terapi Wicara'],
            ['TO', 'Terapi Okupasi'],
            ['SI', 'Sensori Integrasi'],
            ['FT', 'Fisioterapi'],
            ['ABA', 'ABA / Perilaku'],
        ];
        foreach ($types as [$code, $name]) {
            DB::table('therapy_types')->updateOrInsert(['code' => $code], [
                'name' => $name,
                'default_duration_minutes' => 60,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ([['TW', 'Ruang Wicara'], ['TO', 'Ruang Okupasi'], ['SI', 'Ruang Sensori'], ['FT', 'Ruang Fisioterapi']] as [$code, $name]) {
            DB::table('rooms')->updateOrInsert(['code' => $code], [
                'name' => $name,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ([
            ['ASSESSMENT-AWAL', 'Assessment & Konsultasi', 300000, 'Wajib dilakukan pada pertemuan awal sebelum sesi terapi.'],
            ['EVALUASI-24-SESI', 'Evaluasi', 250000, 'Dilakukan setelah 24 sesi terapi.'],
        ] as [$code, $name, $price, $description]) {
            DB::table('service_catalog')->updateOrInsert(['code' => $code], [
                'name' => $name,
                'service_type' => 'assessment',
                'therapy_type_id' => null,
                'price' => $price,
                'description' => $description,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (DB::table('therapy_types')->get() as $type) {
            DB::table('service_catalog')->updateOrInsert(['code' => 'SESI-'.$type->code], [
                'name' => 'Sesi 1 Jam - '.$type->name,
                'service_type' => 'session',
                'therapy_type_id' => $type->id,
                'price' => 160000,
                'description' => 'Satu sesi terapi dengan durasi 1 jam.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('service_catalog')->updateOrInsert(['code' => 'SESI-30-MENIT'], [
            'name' => 'Sesi Terapi 30 Menit',
            'service_type' => 'session',
            'therapy_type_id' => null,
            'price' => 110000,
            'description' => 'Satu sesi terapi dengan durasi 30 menit.',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('package_catalog')->whereIn('name', [
            'Paket TW 4 Sesi',
            'Paket TW 8 Sesi',
            'Paket TW 12 Sesi',
        ])->update(['is_active' => false, 'updated_at' => now()]);

        $packages = [
            ['Paket 1 Jam - Paket 1 (1x Terapi)', 60, 1, 160000],
            ['Paket 1 Jam - Paket 2 (4x Terapi)', 60, 4, 630000],
            ['Paket 1 Jam - Paket 3 (8x Terapi)', 60, 8, 1240000],
            ['Paket 1 Jam - Paket 4 (12x Terapi)', 60, 12, 1800000],
            ['Paket 30 Menit - Paket 1 (1x Terapi)', 30, 1, 110000],
            ['Paket 30 Menit - Paket 2 (6x Terapi)', 30, 6, 570000],
            ['Paket 30 Menit - Paket 3 (12x Terapi)', 30, 12, 1080000],
            ['Paket 30 Menit - Paket 4 (16x Terapi)', 30, 16, 1360000],
        ];

        foreach ($packages as [$packageName, $durationMinutes, $sessions, $price]) {
            DB::table('package_catalog')->updateOrInsert(['name' => $packageName], [
                'validity_days' => 90,
                'price' => $price,
                'session_duration_minutes' => $durationMinutes,
                'session_quantity' => $sessions,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('products')->updateOrInsert(['sku' => 'MEDIA-KARTU-01'], [
            'name' => 'Kartu aktivitas komunikasi', 'price' => 85000, 'stock_quantity' => 20,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
