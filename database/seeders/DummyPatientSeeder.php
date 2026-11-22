<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Assessment;
use App\Models\AssessmentRecommendation;
use App\Models\PackageCatalog;
use App\Models\ParentContact;
use App\Models\Patient;
use App\Models\PatientIntakeSubmission;
use App\Models\PatientPackage;
use App\Models\Room;
use App\Models\Sale;
use App\Models\SessionReport;
use App\Models\TherapistProfile;
use App\Models\TherapyType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class DummyPatientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Pastikan master data dasar tersedia
        if (DB::table('package_catalog')->count() === 0) {
            $this->call(DatabaseSeeder::class);
        }

        // 2. Bersihkan data dummy sebelumnya jika sudah pernah dijalankan agar idempotent
        $this->cleanupPreviousDummyData();

        $cashier = User::where('email', 'kasir@rumahterapi.test')->first()
            ?? User::firstOrCreate(['email' => 'kasir@rumahterapi.test'], [
                'name' => 'Kasir Klinik',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]);

        // Buat akun dan profil terapis spesialis (masing-masing unik untuk patient_therapists constraint)
        $therapists = $this->seedTherapists();

        // Ambil master data
        $therapyTypes = TherapyType::all()->keyBy('code');
        $rooms = Room::all()->keyBy('code');
        $packages = PackageCatalog::all()->keyBy('name');
        $assessmentService = DB::table('service_catalog')->where('code', 'ASSESSMENT-AWAL')->first();

        // =========================================================================
        // KELOMPOK 1: 5 ANAK PASIEN DENGAN PAKET TERAPI BEDA-BEDA
        // =========================================================================

        // 1. Kenzo Alvaro - Paket 1 Jam - Paket 2 (4x Terapi) -> sisa 3 sesi (1 sesi selesai)
        $this->createPatientWithPackage(
            name: 'Kenzo Alvaro',
            birthDate: '2022-05-14',
            gender: 'male',
            complaint: 'Speech delay, artikulasi kata belum jelas dan kosakata terbatas.',
            parentName: 'Hendra Alvaro',
            parentRelation: 'father',
            parentPhone: '081234567001',
            parentAddress: 'Jl. Melati No. 12, Kebayoran Baru, Jakarta Selatan',
            therapyTypeCode: 'TW',
            therapist: $therapists['TW_1'],
            room: $rooms['TW'],
            packageName: 'Paket 1 Jam - Paket 2 (4x Terapi)',
            totalSessions: 4,
            completedSessions: 1,
            assessmentDaysAgo: 21,
            nextSlotDayOffset: 2,
            nextSlotHour: 9,
            cashier: $cashier,
            packages: $packages,
            therapyTypes: $therapyTypes,
            assessmentService: $assessmentService
        );

        // 2. Aisha Humaira - Paket 1 Jam - Paket 3 (8x Terapi) -> sisa 6 sesi (2 sesi selesai)
        $this->createPatientWithPackage(
            name: 'Aisha Humaira',
            birthDate: '2023-08-20',
            gender: 'female',
            complaint: 'Kesulitan fokus, mudah tantrum dan koordinasi motorik halus belum optimal.',
            parentName: 'Fajar Pratama',
            parentRelation: 'father',
            parentPhone: '081234567002',
            parentAddress: 'Jl. Cempaka Putih Timur No. 45, Jakarta Pusat',
            therapyTypeCode: 'OT',
            therapist: $therapists['OT_1'],
            room: $rooms['OT'],
            packageName: 'Paket 1 Jam - Paket 3 (8x Terapi)',
            totalSessions: 8,
            completedSessions: 2,
            assessmentDaysAgo: 30,
            nextSlotDayOffset: 2,
            nextSlotHour: 10,
            cashier: $cashier,
            packages: $packages,
            therapyTypes: $therapyTypes,
            assessmentService: $assessmentService
        );

        // 3. Rayyan Arkanza - Paket 1 Jam - Paket 4 (12x Terapi) -> sisa 8 sesi (4 sesi selesai)
        $this->createPatientWithPackage(
            name: 'Rayyan Arkanza',
            birthDate: '2021-03-10',
            gender: 'male',
            complaint: 'Kesulitan keseimbangan dan koordinasi gerak kasar saat beraktivitas.',
            parentName: 'Dwi Kurniawan',
            parentRelation: 'father',
            parentPhone: '081234567003',
            parentAddress: 'Komp. Bintaro Permai Blok C3 No. 8, Tangerang Selatan',
            therapyTypeCode: 'FT',
            therapist: $therapists['FT_1'],
            room: $rooms['FT'],
            packageName: 'Paket 1 Jam - Paket 4 (12x Terapi)',
            totalSessions: 12,
            completedSessions: 4,
            assessmentDaysAgo: 45,
            nextSlotDayOffset: 3,
            nextSlotHour: 11,
            cashier: $cashier,
            packages: $packages,
            therapyTypes: $therapyTypes,
            assessmentService: $assessmentService
        );

        // 4. Mikayla Zahra - Paket 30 Menit - Paket 2 (6x Terapi) -> sisa 4 sesi (2 sesi selesai)
        $this->createPatientWithPackage(
            name: 'Mikayla Zahra',
            birthDate: '2024-02-15',
            gender: 'female',
            complaint: 'Kontak mata minim, belum konsisten menoleh ketika namanya dipanggil.',
            parentName: 'Rizki Ramadhani',
            parentRelation: 'mother',
            parentPhone: '081234567004',
            parentAddress: 'Jl. Tebet Barat Dalam No. 19, Jakarta Selatan',
            therapyTypeCode: 'TW',
            therapist: $therapists['TW_2'],
            room: $rooms['TW'],
            packageName: 'Paket 30 Menit - Paket 2 (6x Terapi)',
            totalSessions: 6,
            completedSessions: 2,
            assessmentDaysAgo: 25,
            nextSlotDayOffset: 3,
            nextSlotHour: 9,
            cashier: $cashier,
            packages: $packages,
            therapyTypes: $therapyTypes,
            assessmentService: $assessmentService
        );

        // 5. Rafka Daniswara - Paket 30 Menit - Paket 4 (16x Terapi) -> sisa 11 sesi (5 sesi selesai)
        $this->createPatientWithPackage(
            name: 'Rafka Daniswara',
            birthDate: '2020-09-05',
            gender: 'male',
            complaint: 'Perilaku repetitif, interaksi sosial terbatas dan hambatan transisi aktivitas.',
            parentName: 'Agus Setiawan',
            parentRelation: 'father',
            parentPhone: '081234567005',
            parentAddress: 'Jl. Radio Dalam Raya No. 88, Gandaria, Jakarta Selatan',
            therapyTypeCode: 'OT',
            therapist: $therapists['OT_2'],
            room: $rooms['OT'],
            packageName: 'Paket 30 Menit - Paket 4 (16x Terapi)',
            totalSessions: 16,
            completedSessions: 5,
            assessmentDaysAgo: 60,
            nextSlotDayOffset: 4,
            nextSlotHour: 13,
            cashier: $cashier,
            packages: $packages,
            therapyTypes: $therapyTypes,
            assessmentService: $assessmentService
        );

        // =========================================================================
        // KELOMPOK 2: 2 ANAK PASIEN YANG BELUM ASSESSMENT
        // =========================================================================

        // 6. Arka Bimasena - Pasien baru mendaftar, belum dijadwalkan assessment sama sekali
        $this->createPatientWithoutAssessment(
            name: 'Arka Bimasena',
            birthDate: '2023-04-12',
            gender: 'male',
            complaint: 'Speech delay, baru menguasai beberapa kata dasar, pemahaman perintah sederhana lambat.',
            parentName: 'Bima Sakti',
            parentRelation: 'father',
            parentPhone: '081234567006',
            parentAddress: 'Jl. Kemang Timur No. 27, Jakarta Selatan',
            isScheduled: false,
            cashier: $cashier
        );

        // 7. Naura Salsabila - Sudah terdaftar, jadwal assessment ada tetapi BELUM DILAKSANAKAN (scheduled)
        $this->createPatientWithoutAssessment(
            name: 'Naura Salsabila',
            birthDate: '2022-11-28',
            gender: 'female',
            complaint: 'Sering tantrum dan frustrasi ketika tidak dimengerti keinginannya, kesulitan sosialisasi di PAUD.',
            parentName: 'Aditya Pratama',
            parentRelation: 'father',
            parentPhone: '081234567007',
            parentAddress: 'Jl. Margonda Raya No. 102, Depok',
            isScheduled: true,
            cashier: $cashier,
            therapist: $therapists['OT_2'],
            assessmentService: $assessmentService
        );

        // =========================================================================
        // KELOMPOK 3: 1 ANAK YANG SUDAH MENJALANKAN TERAPI 12x DAN PAKETNYA HABIS 0
        // =========================================================================

        // 8. Gibran Al-Fatih - Mengambil Paket 1 Jam 12x, telah menyelesaikan 12 sesi terapi, sisa 0
        $this->createExhaustedPackagePatient(
            name: 'Gibran Al-Fatih',
            birthDate: '2021-07-18',
            gender: 'male',
            complaint: 'Keterlambatan bicara ekspresif dan reseptif, kesulitan artikulasi vokal/konsonan.',
            parentName: 'Wahyu Hidayat',
            parentRelation: 'father',
            parentPhone: '081234567008',
            parentAddress: 'Jl. Fatmawati Raya No. 55, Cilandak, Jakarta Selatan',
            therapyTypeCode: 'TW',
            therapist: $therapists['TW_3'],
            room: $rooms['TW'],
            packageName: 'Paket 1 Jam - Paket 4 (12x Terapi)',
            totalSessions: 12,
            assessmentDaysAgo: 90,
            cashier: $cashier,
            packages: $packages,
            therapyTypes: $therapyTypes,
            assessmentService: $assessmentService
        );
    }

    /**
     * Hapus data dummy lama jika ada agar proses seeding idempotent.
     */
    private function cleanupPreviousDummyData(): void
    {
        $dummyNames = [
            'Kenzo Alvaro',
            'Aisha Humaira',
            'Rayyan Arkanza',
            'Mikayla Zahra',
            'Rafka Daniswara',
            'Arka Bimasena',
            'Naura Salsabila',
            'Gibran Al-Fatih',
        ];

        $patientIds = Patient::whereIn('name', $dummyNames)->pluck('id');

        if ($patientIds->isEmpty()) {
            return;
        }

        // Hapus duration package ledgers & entitlements
        $packageIds = PatientPackage::whereIn('patient_id', $patientIds)->pluck('id');
        $entitlementIds = DB::table('duration_package_entitlements')->whereIn('patient_package_id', $packageIds)->pluck('id');
        DB::table('duration_package_ledger')->whereIn('duration_package_entitlement_id', $entitlementIds)->delete();
        DB::table('duration_package_entitlements')->whereIn('id', $entitlementIds)->delete();
        PatientPackage::whereIn('id', $packageIds)->delete();

        // Hapus appointment reports & appointments
        $appointmentIds = Appointment::whereIn('patient_id', $patientIds)->pluck('id');
        SessionReport::whereIn('appointment_id', $appointmentIds)->delete();
        Appointment::whereIn('id', $appointmentIds)->delete();

        // Hapus assessments
        $assessmentIds = Assessment::whereIn('patient_id', $patientIds)->pluck('id');
        AssessmentRecommendation::whereIn('assessment_id', $assessmentIds)->delete();
        Assessment::whereIn('id', $assessmentIds)->delete();

        // Hapus sales & payments
        $saleIds = Sale::whereIn('patient_id', $patientIds)->pluck('id');
        DB::table('payments')->whereIn('sale_id', $saleIds)->delete();
        DB::table('sale_lines')->whereIn('sale_id', $saleIds)->delete();
        Sale::whereIn('id', $saleIds)->delete();

        // Hapus intake submissions & relations
        PatientIntakeSubmission::whereIn('patient_id', $patientIds)->delete();
        DB::table('patient_therapists')->whereIn('patient_id', $patientIds)->delete();

        $parentIds = DB::table('patient_parents')->whereIn('patient_id', $patientIds)->pluck('parent_id');
        DB::table('patient_parents')->whereIn('patient_id', $patientIds)->delete();
        ParentContact::whereIn('id', $parentIds)->delete();

        Patient::whereIn('id', $patientIds)->delete();
    }

    /**
     * Buat profil terapis dengan akun unik.
     *
     * @return array<string, TherapistProfile>
     */
    private function seedTherapists(): array
    {
        $therapistRole = Role::firstOrCreate(['name' => 'therapist', 'guard_name' => 'web']);

        $definitions = [
            'TW_1' => ['email' => 'terapis.tw1@rumahterapi.test', 'name' => 'Siti Rahmawati, S.Tr.Kes', 'specialty' => 'Terapi Wicara'],
            'OT_1' => ['email' => 'terapis.ot1@rumahterapi.test', 'name' => 'Budi Santoso, S.ST', 'specialty' => 'Terapi Okupasi'],
            'FT_1' => ['email' => 'terapis.ft1@rumahterapi.test', 'name' => 'Dewi Lestari, S.Ft', 'specialty' => 'Fisioterapi'],
            'TW_2' => ['email' => 'terapis.tw2@rumahterapi.test', 'name' => 'Nurul Fadilah, S.Tr.Kes', 'specialty' => 'Terapi Wicara'],
            'TW_3' => ['email' => 'terapis.tw3@rumahterapi.test', 'name' => 'Rahmat Hidayat, S.Tr.Kes', 'specialty' => 'Terapi Wicara'],
            'OT_2' => ['email' => 'terapis.ot2@rumahterapi.test', 'name' => 'Putri Amelia, S.ST', 'specialty' => 'Terapi Okupasi'],
        ];

        $profiles = [];
        foreach ($definitions as $key => $data) {
            $user = User::firstOrCreate(['email' => $data['email']], [
                'name' => $data['name'],
                'password' => Hash::make('password'),
                'is_active' => true,
            ]);
            $user->syncRoles([$therapistRole]);

            $profile = TherapistProfile::firstOrCreate(
                ['user_id' => $user->id],
                ['specialty' => $data['specialty'], 'license_number' => 'STR-'.strtoupper($key).'-'.rand(10000, 99999)]
            );
            $profiles[$key] = $profile;
        }

        return $profiles;
    }

    /**
     * Buat pasien dengan paket aktif yang sedang berjalan.
     */
    private function createPatientWithPackage(
        string $name,
        string $birthDate,
        string $gender,
        string $complaint,
        string $parentName,
        string $parentRelation,
        string $parentPhone,
        string $parentAddress,
        string $therapyTypeCode,
        TherapistProfile $therapist,
        Room $room,
        string $packageName,
        int $totalSessions,
        int $completedSessions,
        int $assessmentDaysAgo,
        int $nextSlotDayOffset,
        int $nextSlotHour,
        User $cashier,
        $packages,
        $therapyTypes,
        $assessmentService
    ): Patient {
        $patient = Patient::create([
            'patient_code' => Patient::nextCode(),
            'name' => $name,
            'birth_date' => $birthDate,
            'gender' => $gender,
            'address' => $parentAddress,
            'primary_whatsapp' => $parentPhone,
            'main_complaint' => $complaint,
            'is_active' => true,
            'created_by' => $cashier->id,
            'created_at' => Carbon::now()->subDays($assessmentDaysAgo + 7),
        ]);

        $parent = ParentContact::create([
            'name' => $parentName,
            'phone' => $parentPhone,
            'whatsapp' => $parentPhone,
            'email' => Str::slug($parentName).'@gmail.com',
            'address' => $parentAddress,
        ]);

        $patient->parents()->attach($parent->id, [
            'relation' => $parentRelation,
            'is_primary_contact' => true,
            'can_access_portal' => true,
        ]);

        $therapyType = $therapyTypes[$therapyTypeCode];
        $patient->syncTherapistAssignments([
            $therapyTypeCode => $therapist->id,
        ]);

        // 1. Assessment Selesai
        $assessmentDate = Carbon::now()->subDays($assessmentDaysAgo);
        $assessmentSale = Sale::create([
            'invoice_number' => 'INV-'.str_replace('-', '', $assessmentDate->format('Y-m-d')).'-'.Str::upper(Str::random(5)),
            'patient_id' => $patient->id,
            'cashier_id' => $cashier->id,
            'status' => 'paid',
            'subtotal' => $assessmentService->price ?? 300000,
            'discount_total' => 0,
            'grand_total' => $assessmentService->price ?? 300000,
            'paid_at' => $assessmentDate->copy()->subHours(2),
            'created_at' => $assessmentDate->copy()->subHours(2),
        ]);

        $assessmentSale->lines()->create([
            'line_type' => 'assessment',
            'service_catalog_id' => $assessmentService->id ?? null,
            'description' => $assessmentService->name ?? 'Assessment & Konsultasi',
            'quantity' => 1,
            'unit_price' => $assessmentService->price ?? 300000,
            'line_total' => $assessmentService->price ?? 300000,
        ]);

        $assessmentSale->payment()->create([
            'method' => 'bank_transfer',
            'bank_name' => 'BCA',
            'amount' => $assessmentSale->grand_total,
            'reference_number' => 'TRF-'.rand(100000, 999999),
            'received_by' => $cashier->id,
            'received_at' => $assessmentSale->paid_at,
        ]);

        $assessment = Assessment::create([
            'patient_id' => $patient->id,
            'sale_id' => $assessmentSale->id,
            'therapist_profile_id' => $therapist->id,
            'status' => 'completed',
            'scheduled_at' => $assessmentDate,
            'completed_at' => $assessmentDate->copy()->addHour(),
            'chief_complaint' => $complaint,
            'summary' => "Pasien {$name} menunjukkan potensi respon yang baik, disarankan mengikuti terapi {$therapyType->name} secara konsisten.",
            'created_by' => $cashier->id,
            'created_at' => $assessmentDate,
        ]);

        AssessmentRecommendation::create([
            'assessment_id' => $assessment->id,
            'therapy_type_id' => $therapyType->id,
            'sessions_per_week' => 2,
            'duration_minutes' => $packages[$packageName]->session_duration_minutes ?? 60,
            'notes' => 'Fokus pada stimulasi komunikasi dua arah dan penguatan atensi.',
        ]);

        // 2. Pembelian Paket
        $packageCatalog = $packages[$packageName];
        $packageStartDate = $assessmentDate->copy()->addDays(2);
        $packageSale = Sale::create([
            'invoice_number' => 'INV-'.str_replace('-', '', $packageStartDate->format('Y-m-d')).'-'.Str::upper(Str::random(5)),
            'patient_id' => $patient->id,
            'cashier_id' => $cashier->id,
            'therapist_profile_id' => $therapist->id,
            'status' => 'paid',
            'subtotal' => $packageCatalog->price,
            'discount_total' => 0,
            'grand_total' => $packageCatalog->price,
            'paid_at' => $packageStartDate,
            'created_at' => $packageStartDate,
        ]);

        $packageSale->lines()->create([
            'line_type' => 'package',
            'package_catalog_id' => $packageCatalog->id,
            'description' => $packageCatalog->name,
            'quantity' => 1,
            'unit_price' => $packageCatalog->price,
            'line_total' => $packageCatalog->price,
        ]);

        $packageSale->payment()->create([
            'method' => 'qris',
            'amount' => $packageSale->grand_total,
            'reference_number' => 'QRIS-'.rand(100000, 999999),
            'received_by' => $cashier->id,
            'received_at' => $packageStartDate,
        ]);

        $patientPackage = PatientPackage::create([
            'patient_id' => $patient->id,
            'package_catalog_id' => $packageCatalog->id,
            'sale_id' => $packageSale->id,
            'status' => 'active',
            'starts_at' => $packageStartDate,
            'expires_at' => $packageStartDate->copy()->addDays($packageCatalog->validity_days),
        ]);

        $remainingSessions = $totalSessions - $completedSessions;
        $entitlementId = DB::table('duration_package_entitlements')->insertGetId([
            'patient_package_id' => $patientPackage->id,
            'duration_minutes' => $packageCatalog->session_duration_minutes,
            'granted_sessions' => $totalSessions,
            'remaining_sessions' => $remainingSessions,
        ]);

        DB::table('duration_package_ledger')->insert([
            'duration_package_entitlement_id' => $entitlementId,
            'appointment_id' => null,
            'quantity_delta' => $totalSessions,
            'reason' => 'package_purchase',
            'created_by' => $cashier->id,
            'created_at' => $packageStartDate,
        ]);

        // 3. Sesi Terapi yang Sudah Selesai
        $sessionDuration = $packageCatalog->session_duration_minutes;
        for ($i = 0; $i < $completedSessions; $i++) {
            $sessionDate = $packageStartDate->copy()->addDays(($i + 1) * 3)->setHour(9 + ($i % 3))->setMinute(0)->setSecond(0);

            $appointment = Appointment::create([
                'patient_id' => $patient->id,
                'therapy_type_id' => $therapyType->id,
                'therapist_profile_id' => $therapist->id,
                'room_id' => $room->id,
                'starts_at' => $sessionDate,
                'ends_at' => $sessionDate->copy()->addMinutes($sessionDuration),
                'status' => 'completed',
                'booking_source' => 'admin',
                'attendance_status' => 'present',
                'created_by' => $cashier->id,
                'created_at' => $sessionDate->copy()->subDays(2),
            ]);

            SessionReport::create([
                'appointment_id' => $appointment->id,
                'therapist_user_id' => $therapist->user_id,
                'status' => 'submitted',
                'activities' => 'Latihan sesi ke-'.($i + 1).': Stimulasi fokus, koordinasi dan respon kontak mata melalui media bermain interaktif.',
                'parent_notes' => "Anak {$name} kooperatif dan menunjukkan antusiasme yang meningkat selama sesi berlangsung.",
                'homework' => 'Lanjutkan latihan stimulasi komunikasi 15 menit setiap pagi di rumah.',
                'submitted_at' => $sessionDate->copy()->addMinutes($sessionDuration + 10),
            ]);

            DB::table('duration_package_ledger')->insert([
                'duration_package_entitlement_id' => $entitlementId,
                'appointment_id' => $appointment->id,
                'quantity_delta' => -1,
                'reason' => 'session_completed',
                'created_by' => $therapist->user_id,
                'created_at' => $sessionDate->copy()->addMinutes($sessionDuration + 10),
            ]);
        }

        // 4. Jadwalkan 1 sesi mendatang jika kuota masih ada
        if ($remainingSessions > 0) {
            $nextSessionDate = Carbon::now()->addDays($nextSlotDayOffset)->setHour($nextSlotHour)->setMinute(0)->setSecond(0);
            Appointment::create([
                'patient_id' => $patient->id,
                'therapy_type_id' => $therapyType->id,
                'therapist_profile_id' => $therapist->id,
                'room_id' => $room->id,
                'starts_at' => $nextSessionDate,
                'ends_at' => $nextSessionDate->copy()->addMinutes($sessionDuration),
                'status' => 'scheduled',
                'booking_source' => 'admin',
                'created_by' => $cashier->id,
                'created_at' => Carbon::now()->subDay(),
            ]);
        }

        return $patient;
    }

    /**
     * Buat pasien yang belum assessment.
     */
    private function createPatientWithoutAssessment(
        string $name,
        string $birthDate,
        string $gender,
        string $complaint,
        string $parentName,
        string $parentRelation,
        string $parentPhone,
        string $parentAddress,
        bool $isScheduled,
        User $cashier,
        ?TherapistProfile $therapist = null,
        $assessmentService = null
    ): Patient {
        $patient = Patient::create([
            'patient_code' => Patient::nextCode(),
            'name' => $name,
            'birth_date' => $birthDate,
            'gender' => $gender,
            'address' => $parentAddress,
            'primary_whatsapp' => $parentPhone,
            'main_complaint' => $complaint,
            'is_active' => true,
            'created_by' => $cashier->id,
            'created_at' => Carbon::now()->subDays(3),
        ]);

        $parent = ParentContact::create([
            'name' => $parentName,
            'phone' => $parentPhone,
            'whatsapp' => $parentPhone,
            'email' => Str::slug($parentName).'@gmail.com',
            'address' => $parentAddress,
        ]);

        $patient->parents()->attach($parent->id, [
            'relation' => $parentRelation,
            'is_primary_contact' => true,
            'can_access_portal' => true,
        ]);

        // Buat data intake submission formulir pendaftaran
        PatientIntakeSubmission::create([
            'source' => 'web_registration',
            'response_id' => 'RESP-'.Str::upper(Str::random(8)),
            'submitted_at' => Carbon::now()->subDays(3),
            'child_data' => [
                'name' => $name,
                'birth_date' => $birthDate,
                'gender' => $gender,
                'complaint' => $complaint,
            ],
            'father_data' => [
                'name' => $parentName,
                'phone' => $parentPhone,
            ],
            'mother_data' => [
                'name' => 'Ibu '.$parentName,
                'phone' => $parentPhone,
            ],
            'family_context' => [
                'address' => $parentAddress,
            ],
            'development_history' => [
                'milestones' => 'Perkembangan motorik sesuai usia, stimulasi bahasa memerlukan evaluasi spesialis.',
            ],
            'health_history' => [
                'allergies' => 'Tidak ada',
            ],
            'verification_status' => $isScheduled ? 'verified' : 'pending',
            'patient_id' => $patient->id,
        ]);

        // Jika sudah dijadwalkan, buat jadwal assessment mendatang (status scheduled, completed_at null)
        if ($isScheduled && $therapist && $assessmentService) {
            $scheduledDate = Carbon::now()->addDays(4)->setHour(10)->setMinute(0)->setSecond(0);

            $assessmentSale = Sale::create([
                'invoice_number' => 'INV-'.str_replace('-', '', now()->format('Y-m-d')).'-'.Str::upper(Str::random(5)),
                'patient_id' => $patient->id,
                'cashier_id' => $cashier->id,
                'status' => 'pending',
                'subtotal' => $assessmentService->price ?? 300000,
                'discount_total' => 0,
                'grand_total' => $assessmentService->price ?? 300000,
                'paid_at' => null,
            ]);

            $assessmentSale->lines()->create([
                'line_type' => 'assessment',
                'service_catalog_id' => $assessmentService->id ?? null,
                'description' => $assessmentService->name ?? 'Assessment & Konsultasi',
                'quantity' => 1,
                'unit_price' => $assessmentService->price ?? 300000,
                'line_total' => $assessmentService->price ?? 300000,
            ]);

            Assessment::create([
                'patient_id' => $patient->id,
                'sale_id' => $assessmentSale->id,
                'therapist_profile_id' => $therapist->id,
                'status' => 'scheduled',
                'scheduled_at' => $scheduledDate,
                'completed_at' => null,
                'chief_complaint' => $complaint,
                'created_by' => $cashier->id,
            ]);
        }

        return $patient;
    }

    /**
     * Buat 1 anak pasien yang sudah menjalankan 12x terapi dan paketnya habis (0 sesi tersisa).
     */
    private function createExhaustedPackagePatient(
        string $name,
        string $birthDate,
        string $gender,
        string $complaint,
        string $parentName,
        string $parentRelation,
        string $parentPhone,
        string $parentAddress,
        string $therapyTypeCode,
        TherapistProfile $therapist,
        Room $room,
        string $packageName,
        int $totalSessions,
        int $assessmentDaysAgo,
        User $cashier,
        $packages,
        $therapyTypes,
        $assessmentService
    ): Patient {
        $patient = Patient::create([
            'patient_code' => Patient::nextCode(),
            'name' => $name,
            'birth_date' => $birthDate,
            'gender' => $gender,
            'address' => $parentAddress,
            'primary_whatsapp' => $parentPhone,
            'main_complaint' => $complaint,
            'is_active' => true,
            'created_by' => $cashier->id,
            'created_at' => Carbon::now()->subDays($assessmentDaysAgo + 14),
        ]);

        $parent = ParentContact::create([
            'name' => $parentName,
            'phone' => $parentPhone,
            'whatsapp' => $parentPhone,
            'email' => Str::slug($parentName).'@gmail.com',
            'address' => $parentAddress,
        ]);

        $patient->parents()->attach($parent->id, [
            'relation' => $parentRelation,
            'is_primary_contact' => true,
            'can_access_portal' => true,
        ]);

        $therapyType = $therapyTypes[$therapyTypeCode];
        $patient->syncTherapistAssignments([
            $therapyTypeCode => $therapist->id,
        ]);

        // 1. Assessment awal selesai
        $assessmentDate = Carbon::now()->subDays($assessmentDaysAgo);
        $assessmentSale = Sale::create([
            'invoice_number' => 'INV-'.str_replace('-', '', $assessmentDate->format('Y-m-d')).'-'.Str::upper(Str::random(5)),
            'patient_id' => $patient->id,
            'cashier_id' => $cashier->id,
            'status' => 'paid',
            'subtotal' => $assessmentService->price ?? 300000,
            'discount_total' => 0,
            'grand_total' => $assessmentService->price ?? 300000,
            'paid_at' => $assessmentDate->copy()->subHours(2),
            'created_at' => $assessmentDate->copy()->subHours(2),
        ]);

        $assessmentSale->lines()->create([
            'line_type' => 'assessment',
            'service_catalog_id' => $assessmentService->id ?? null,
            'description' => $assessmentService->name ?? 'Assessment & Konsultasi',
            'quantity' => 1,
            'unit_price' => $assessmentService->price ?? 300000,
            'line_total' => $assessmentService->price ?? 300000,
        ]);

        $assessmentSale->payment()->create([
            'method' => 'bank_transfer',
            'bank_name' => 'Mandiri',
            'amount' => $assessmentSale->grand_total,
            'reference_number' => 'TRF-'.rand(100000, 999999),
            'received_by' => $cashier->id,
            'received_at' => $assessmentSale->paid_at,
        ]);

        $assessment = Assessment::create([
            'patient_id' => $patient->id,
            'sale_id' => $assessmentSale->id,
            'therapist_profile_id' => $therapist->id,
            'status' => 'completed',
            'scheduled_at' => $assessmentDate,
            'completed_at' => $assessmentDate->copy()->addHour(),
            'chief_complaint' => $complaint,
            'summary' => "Pasien {$name} mengalami keterlambatan bicara sedang. Direkomendasikan terapi wicara intensif 12 sesi.",
            'created_by' => $cashier->id,
            'created_at' => $assessmentDate,
        ]);

        AssessmentRecommendation::create([
            'assessment_id' => $assessment->id,
            'therapy_type_id' => $therapyType->id,
            'sessions_per_week' => 2,
            'duration_minutes' => 60,
            'notes' => 'Rekomendasi paket 12 sesi dengan evaluasi di akhir paket.',
        ]);

        // 2. Pembelian Paket 12 Sesi
        $packageCatalog = $packages[$packageName];
        $packageStartDate = $assessmentDate->copy()->addDays(2);
        $packageSale = Sale::create([
            'invoice_number' => 'INV-'.str_replace('-', '', $packageStartDate->format('Y-m-d')).'-'.Str::upper(Str::random(5)),
            'patient_id' => $patient->id,
            'cashier_id' => $cashier->id,
            'therapist_profile_id' => $therapist->id,
            'status' => 'paid',
            'subtotal' => $packageCatalog->price,
            'discount_total' => 0,
            'grand_total' => $packageCatalog->price,
            'paid_at' => $packageStartDate,
            'created_at' => $packageStartDate,
        ]);

        $packageSale->lines()->create([
            'line_type' => 'package',
            'package_catalog_id' => $packageCatalog->id,
            'description' => $packageCatalog->name,
            'quantity' => 1,
            'unit_price' => $packageCatalog->price,
            'line_total' => $packageCatalog->price,
        ]);

        $packageSale->payment()->create([
            'method' => 'bank_transfer',
            'bank_name' => 'BCA',
            'amount' => $packageSale->grand_total,
            'reference_number' => 'TRF-'.rand(100000, 999999),
            'received_by' => $cashier->id,
            'received_at' => $packageStartDate,
        ]);

        // Status paket 'completed' karena sisa kuota sudah 0
        $patientPackage = PatientPackage::create([
            'patient_id' => $patient->id,
            'package_catalog_id' => $packageCatalog->id,
            'sale_id' => $packageSale->id,
            'status' => 'completed',
            'starts_at' => $packageStartDate,
            'expires_at' => $packageStartDate->copy()->addDays($packageCatalog->validity_days),
        ]);

        $entitlementId = DB::table('duration_package_entitlements')->insertGetId([
            'patient_package_id' => $patientPackage->id,
            'duration_minutes' => $packageCatalog->session_duration_minutes,
            'granted_sessions' => $totalSessions,
            'remaining_sessions' => 0, // Habis 0!
        ]);

        DB::table('duration_package_ledger')->insert([
            'duration_package_entitlement_id' => $entitlementId,
            'appointment_id' => null,
            'quantity_delta' => $totalSessions,
            'reason' => 'package_purchase',
            'created_by' => $cashier->id,
            'created_at' => $packageStartDate,
        ]);

        // 3. Eksekusi 12x Sesi Terapi Lengkap Beserta Laporan Sesi dan Ledger
        $sessionDuration = $packageCatalog->session_duration_minutes;
        for ($i = 0; $i < $totalSessions; $i++) {
            $daysOffset = 3 + ($i * 4);
            $sessionDate = $packageStartDate->copy()->addDays($daysOffset)->setHour(13)->setMinute(0)->setSecond(0);

            $appointment = Appointment::create([
                'patient_id' => $patient->id,
                'therapy_type_id' => $therapyType->id,
                'therapist_profile_id' => $therapist->id,
                'room_id' => $room->id,
                'starts_at' => $sessionDate,
                'ends_at' => $sessionDate->copy()->addMinutes($sessionDuration),
                'status' => 'completed',
                'booking_source' => 'admin',
                'attendance_status' => 'present',
                'created_by' => $cashier->id,
                'created_at' => $sessionDate->copy()->subDays(2),
            ]);

            SessionReport::create([
                'appointment_id' => $appointment->id,
                'therapist_user_id' => $therapist->user_id,
                'status' => 'submitted',
                'activities' => 'Sesi Terapi ke-'.($i + 1).' (Program 12x Sesi): Latihan artikulasi kata, fonasi, dan penguatan otot oromotor.',
                'parent_notes' => 'Perkembangan sesi ke-'.($i + 1).': Anak menunjukkan kemajuan konsisten dalam pelafalan konsonan bilabial.',
                'homework' => 'Latihan pernapasan dan tirukan suara vokal bersama orang tua di rumah selama 15 menit.',
                'submitted_at' => $sessionDate->copy()->addMinutes($sessionDuration + 15),
            ]);

            DB::table('duration_package_ledger')->insert([
                'duration_package_entitlement_id' => $entitlementId,
                'appointment_id' => $appointment->id,
                'quantity_delta' => -1,
                'reason' => 'session_completed',
                'created_by' => $therapist->user_id,
                'created_at' => $sessionDate->copy()->addMinutes($sessionDuration + 15),
            ]);
        }

        return $patient;
    }
}
