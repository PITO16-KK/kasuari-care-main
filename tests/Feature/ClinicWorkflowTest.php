<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Assessment;
use App\Models\PackageCatalog;
use App\Models\PackageEntitlement;
use App\Models\ParentContact;
use App\Models\Patient;
use App\Models\PatientPackage;
use App\Models\Room;
use App\Models\Sale;
use App\Models\TherapistProfile;
use App\Models\TherapyType;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClinicWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_is_accepted_without_staff_login(): void
    {
        $response = $this->post('/daftar', [
            'child_name' => 'Nadia Putri',
            'parent_name' => 'Rina Putri',
            'parent_relation' => 'mother',
            'whatsapp' => '081234567890',
            'complaint' => 'Terlambat bicara',
        ]);

        $response->assertRedirect('/daftar');
        $this->assertDatabaseHas('public_registrations', [
            'child_name' => 'Nadia Putri',
            'whatsapp' => '081234567890',
            'status' => 'new',
        ]);
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_dashboard_today_schedule_includes_all_therapy_assessment_and_evaluation_agendas(): void
    {
        $this->travelTo('2026-10-25 07:00:00');
        $owner = $this->userWithRole('owner');
        $patient = $this->patient();
        $therapy = TherapyType::create(['code' => 'TW', 'name' => 'Terapi Wicara', 'default_duration_minutes' => 60]);

        foreach (range(0, 8) as $hourOffset) {
            $startsAt = now()->startOfDay()->addHours(8 + $hourOffset);
            Appointment::create([
                'patient_id' => $patient->id,
                'therapy_type_id' => $therapy->id,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addHour(),
                'status' => 'scheduled',
            ]);
        }

        Appointment::create([
            'patient_id' => $patient->id,
            'therapy_type_id' => $therapy->id,
            'starts_at' => now()->startOfDay()->addHours(19),
            'ends_at' => now()->startOfDay()->addHours(20),
            'status' => 'cancelled',
        ]);

        foreach ([
            ['INV-ASSESSMENT', 'Assessment & Konsultasi', 'scheduled', 17],
            ['INV-EVALUASI', 'Evaluasi', 'awaiting_payment', 18],
        ] as [$invoiceNumber, $serviceName, $status, $hour]) {
            $sale = Sale::create([
                'invoice_number' => $invoiceNumber,
                'patient_id' => $patient->id,
                'status' => $status === 'awaiting_payment' ? 'pending' : 'paid',
                'subtotal' => 300000,
                'discount_total' => 0,
                'grand_total' => 300000,
                'paid_at' => $status === 'awaiting_payment' ? null : now(),
            ]);
            $sale->lines()->create([
                'line_type' => 'assessment',
                'description' => $serviceName,
                'quantity' => 1,
                'unit_price' => 300000,
                'line_total' => 300000,
            ]);
            Assessment::create([
                'patient_id' => $patient->id,
                'sale_id' => $sale->id,
                'scheduled_at' => now()->startOfDay()->addHours($hour),
                'status' => $status,
            ]);
        }

        Assessment::create([
            'patient_id' => $patient->id,
            'scheduled_at' => now()->startOfDay()->addHours(21),
            'status' => 'completed',
        ]);

        $this->actingAs($owner)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('stats.todayAppointments', 11)
                ->has('todaySchedule', 11)
                ->where('todaySchedule.0.activity_name', 'Terapi Wicara')
                ->where('todaySchedule.9.activity_name', 'Assessment & Konsultasi')
                ->where('todaySchedule.10.activity_name', 'Evaluasi'));
    }

    public function test_dashboard_schedule_shows_each_patients_package_session_progress(): void
    {
        $this->travelTo('2026-10-25 07:00:00');
        $owner = $this->userWithRole('owner');
        $therapist = $this->userWithRole('therapist');
        $patients = [
            $this->patient(),
            Patient::create([
                'patient_code' => 'KL-2026-002',
                'name' => 'Pasien Kedua',
                'is_active' => true,
            ]),
            Patient::create([
                'patient_code' => 'KL-2026-003',
                'name' => 'Pasien Ketiga',
                'is_active' => true,
            ]),
        ];
        $therapy = TherapyType::create(['code' => 'TW', 'name' => 'Terapi Wicara', 'default_duration_minutes' => 60]);

        foreach ([
            ['name' => 'Paket 8 sesi', 'duration' => 30, 'total' => 8, 'remaining' => 7, 'hour' => 9],
            ['name' => 'Paket 4 sesi', 'duration' => 60, 'total' => 4, 'remaining' => 3, 'hour' => 11],
            ['name' => 'Paket terapi lama', 'duration' => 60, 'total' => 5, 'remaining' => 4, 'hour' => 13, 'legacy' => true],
        ] as $index => $packageData) {
            $packageCatalogId = DB::table('package_catalog')->insertGetId([
                'name' => $packageData['name'],
                'validity_days' => 30,
                'price' => 500000,
                'session_duration_minutes' => $packageData['duration'],
                'session_quantity' => $packageData['total'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $patientPackage = PatientPackage::create([
                'patient_id' => $patients[$index]->id,
                'package_catalog_id' => $packageCatalogId,
                'status' => 'active',
                'starts_at' => now()->subDay(),
                'expires_at' => now()->addDays(29),
            ]);
            if ($packageData['legacy'] ?? false) {
                DB::table('package_entitlements')->insert([
                    'patient_package_id' => $patientPackage->id,
                    'therapy_type_id' => $therapy->id,
                    'granted_sessions' => $packageData['total'],
                    'remaining_sessions' => $packageData['remaining'],
                ]);
            } else {
                $entitlementId = DB::table('duration_package_entitlements')->insertGetId([
                    'patient_package_id' => $patientPackage->id,
                    'duration_minutes' => $packageData['duration'],
                    'granted_sessions' => $packageData['total'],
                    'remaining_sessions' => $packageData['remaining'],
                ]);
            }

            if ($index === 0) {
                $completedStartsAt = now()->startOfDay()->addHours(8);
                $completedAppointment = Appointment::create([
                    'patient_id' => $patients[$index]->id,
                    'therapy_type_id' => $therapy->id,
                    'starts_at' => $completedStartsAt,
                    'ends_at' => $completedStartsAt->copy()->addMinutes($packageData['duration']),
                    'status' => 'completed',
                    'attendance_status' => 'present',
                ]);
                DB::table('session_reports')->insert([
                    'appointment_id' => $completedAppointment->id,
                    'therapist_user_id' => $therapist->id,
                    'status' => 'submitted',
                    'submitted_at' => $completedStartsAt->copy()->addMinutes(30),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('duration_package_ledger')->insert([
                    'duration_package_entitlement_id' => $entitlementId,
                    'appointment_id' => $completedAppointment->id,
                    'quantity_delta' => -1,
                    'reason' => 'session_completed',
                    'created_by' => $therapist->id,
                    'created_at' => $completedStartsAt->copy()->addMinutes(30),
                ]);
            }

            $startsAt = now()->startOfDay()->addHours($packageData['hour']);

            Appointment::create([
                'patient_id' => $patients[$index]->id,
                'therapy_type_id' => $therapy->id,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addMinutes($packageData['duration']),
                'status' => 'scheduled',
            ]);
        }

        $this->actingAs($owner)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('todaySchedule.0.package_session.number', 1)
                ->where('todaySchedule.0.package_session.total', 8)
                ->where('todaySchedule.0.package_session.package_name', 'Paket 8 sesi')
                ->where('todaySchedule.0.package_session.package_number', 1)
                ->where('todaySchedule.1.package_session.number', 2)
                ->where('todaySchedule.1.package_session.total', 8)
                ->where('todaySchedule.1.package_session.package_name', 'Paket 8 sesi')
                ->where('todaySchedule.2.package_session.number', 2)
                ->where('todaySchedule.2.package_session.total', 4)
                ->where('todaySchedule.2.package_session.package_name', 'Paket 4 sesi')
                ->where('todaySchedule.3.package_session.number', 2)
                ->where('todaySchedule.3.package_session.total', 5)
                ->where('todaySchedule.3.package_session.package_name', 'Paket terapi lama'));
    }

    public function test_dashboard_schedule_filters_by_date_therapist_and_therapy_and_updates_summary(): void
    {
        $this->travelTo('2026-10-25 07:00:00');
        $owner = $this->userWithRole('owner');
        $firstPatient = $this->patient();
        $secondPatient = Patient::create([
            'patient_code' => 'KL-2026-002',
            'name' => 'Pasien Kedua',
            'is_active' => true,
        ]);
        $firstTherapistUser = User::factory()->create(['name' => 'Terapis Wati']);
        $secondTherapistUser = User::factory()->create(['name' => 'Terapis Budi']);
        $firstTherapist = TherapistProfile::create(['user_id' => $firstTherapistUser->id]);
        $secondTherapist = TherapistProfile::create(['user_id' => $secondTherapistUser->id]);
        $speechTherapy = TherapyType::create(['code' => 'TW', 'name' => 'Terapi Wicara', 'default_duration_minutes' => 60]);
        $occupationalTherapy = TherapyType::create(['code' => 'OT', 'name' => 'Terapi Okupasi', 'default_duration_minutes' => 60]);
        $selectedDate = now()->addDay()->startOfDay();

        foreach ([
            [$firstPatient, $speechTherapy, $firstTherapist, 'completed'],
            [$secondPatient, $speechTherapy, $secondTherapist, 'scheduled'],
            [$secondPatient, $occupationalTherapy, $firstTherapist, 'scheduled'],
        ] as [$patient, $therapy, $therapist, $status]) {
            $startsAt = $selectedDate->copy()->setTime(9 + $patient->id, 0);
            Appointment::create([
                'patient_id' => $patient->id,
                'therapy_type_id' => $therapy->id,
                'therapist_profile_id' => $therapist->id,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addHour(),
                'status' => $status,
                'attendance_status' => $status === 'completed' ? 'present' : null,
            ]);
        }

        $nextDayStart = $selectedDate->copy()->addDay()->setTime(9, 0);
        Appointment::create([
            'patient_id' => $firstPatient->id,
            'therapy_type_id' => $speechTherapy->id,
            'therapist_profile_id' => $firstTherapist->id,
            'starts_at' => $nextDayStart,
            'ends_at' => $nextDayStart->copy()->addHour(),
            'status' => 'scheduled',
        ]);

        $this->actingAs($owner)->get('/dashboard?date=2026-10-26&therapist_id='.$firstTherapist->id.'&therapy_type_id='.$speechTherapy->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('scheduleFilters.date', '2026-10-26')
                ->has('therapySchedule', 1)
                ->where('therapySchedule.0.patient.name', 'Pasien Uji')
                ->where('therapySchedule.0.therapy_type.code', 'TW')
                ->where('therapySchedule.0.therapist_name', 'Terapis Wati')
                ->where('therapySchedule.0.attendance_status', 'completed'));
    }

    public function test_scheduling_agenda_includes_the_patients_primary_whatsapp_number(): void
    {
        $this->travelTo('2026-10-25 07:00:00');
        $owner = $this->userWithRole('owner');
        $patient = $this->patient();
        $patient->update(['primary_whatsapp' => '081234567890']);
        $therapy = TherapyType::create(['code' => 'TW', 'name' => 'Terapi Wicara', 'default_duration_minutes' => 60]);
        $startsAt = now()->addHour();
        Appointment::create([
            'patient_id' => $patient->id,
            'therapy_type_id' => $therapy->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'status' => 'scheduled',
        ]);

        $this->actingAs($owner)->get('/scheduling')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Scheduling/Index')
                ->has('appointments', 1)
                ->where('appointments.0.patient.primary_whatsapp', '081234567890'));
    }

    public function test_assessment_is_saved_and_returns_a_separate_pdf_link(): void
    {
        $owner = $this->userWithRole('owner');
        $this->actingAs($owner);
        $patient = $this->patient();

        $response = $this->post('/assessments', [
            'patient_id' => $patient->id,
            'scheduled_at' => '2020-01-01 10:30:00',
            'chief_complaint' => 'Perkembangan bicara belum sesuai usia.',
            'development_history' => ['motorik' => 'Baik', 'sensorik' => '', 'kemandirian' => '', 'komunikasi' => 'Kata masih terbatas.'],
            'learning_behavior' => ['belajar' => '', 'fokus' => '', 'perilaku' => ''],
            'summary' => 'Perlu pemeriksaan lanjutan.',
            'referral_notes' => '',
            'recommendations' => [],
        ]);

        $response->assertRedirect('/assessments/create');
        $assessmentId = DB::table('assessments')->value('id');
        $this->assertNotNull($assessmentId);
        $this->assertDatabaseHas('assessments', [
            'id' => $assessmentId,
            'patient_id' => $patient->id,
            'scheduled_at' => '2020-01-01 10:30:00',
            'chief_complaint' => 'Perkembangan bicara belum sesuai usia.',
            'status' => 'scheduled',
        ]);
        $response->assertSessionHas('assessmentPdfUrl', route('assessments.pdf', $assessmentId));
    }

    public function test_assessment_shows_validation_error_for_an_incomplete_recommendation(): void
    {
        $owner = $this->userWithRole('owner');
        $this->actingAs($owner);
        $patient = $this->patient();

        $this->post('/assessments', [
            'patient_id' => $patient->id,
            'chief_complaint' => 'Perlu assessment awal.',
            'recommendations' => [['therapy_type_id' => '', 'sessions_per_week' => 1]],
        ])->assertSessionHasErrors('recommendations.0.therapy_type_id');

        $this->assertDatabaseCount('assessments', 0);
    }

    public function test_assessment_schedule_saves_the_selected_therapist_and_rejects_overlapping_therapy(): void
    {
        $this->travelTo('2026-10-25 07:00:00');
        $owner = $this->userWithRole('owner');
        $patient = $this->patient();
        $otherPatient = Patient::create(['patient_code' => 'KL-2026-002', 'name' => 'Pasien Terapi', 'is_active' => true]);
        $therapist = User::factory()->create();
        $profile = TherapistProfile::create(['user_id' => $therapist->id, 'specialty' => 'Wicara']);
        $therapy = TherapyType::create(['code' => 'TW', 'name' => 'Terapi Wicara', 'default_duration_minutes' => 60]);
        $startsAt = now()->addDay()->setTime(10, 0);
        Appointment::create([
            'patient_id' => $otherPatient->id,
            'therapy_type_id' => $therapy->id,
            'therapist_profile_id' => $profile->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'status' => 'scheduled',
        ]);
        $otherAssessmentStart = $startsAt->copy()->addHours(3);
        Assessment::create([
            'patient_id' => $otherPatient->id,
            'therapist_profile_id' => $profile->id,
            'scheduled_at' => $otherAssessmentStart,
            'status' => 'paid',
        ]);
        $serviceId = $this->assessmentService();
        $this->actingAs($owner)->get('/assessments/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Assessments/Create')
                ->where('therapists.0.id', $profile->id));

        $this->from('/assessments/create')->post('/assessments', [
            'patient_id' => $patient->id,
            'therapist_profile_id' => $profile->id,
            'scheduled_at' => $startsAt->copy()->addMinutes(30)->format('Y-m-d H:i:s'),
            'service_catalog_id' => $serviceId,
        ])->assertSessionHasErrors('therapist_profile_id');

        $this->from('/assessments/create')->post('/assessments', [
            'patient_id' => $patient->id,
            'therapist_profile_id' => $profile->id,
            'scheduled_at' => $otherAssessmentStart->copy()->addMinutes(30)->format('Y-m-d H:i:s'),
            'service_catalog_id' => $serviceId,
        ])->assertSessionHasErrors('therapist_profile_id');

        $this->assertDatabaseCount('assessments', 1);
        $this->assertDatabaseCount('sales', 0);

        $this->post('/assessments', [
            'patient_id' => $patient->id,
            'therapist_profile_id' => $profile->id,
            'scheduled_at' => $startsAt->copy()->addHour()->format('Y-m-d H:i:s'),
            'service_catalog_id' => $serviceId,
        ])->assertRedirect('/assessments');

        $this->assertDatabaseHas('assessments', [
            'patient_id' => $patient->id,
            'therapist_profile_id' => $profile->id,
            'status' => 'awaiting_payment',
        ]);
    }

    public function test_assessment_rescheduling_rejects_an_overlapping_therapy_session(): void
    {
        $this->travelTo('2026-10-25 07:00:00');
        $owner = $this->userWithRole('owner');
        $patient = $this->patient();
        $otherPatient = Patient::create(['patient_code' => 'KL-2026-002', 'name' => 'Pasien Terapi', 'is_active' => true]);
        $therapist = User::factory()->create();
        $profile = TherapistProfile::create(['user_id' => $therapist->id]);
        $therapy = TherapyType::create(['code' => 'TW', 'name' => 'Terapi Wicara', 'default_duration_minutes' => 60]);
        $assessment = Assessment::create([
            'patient_id' => $patient->id,
            'therapist_profile_id' => $profile->id,
            'scheduled_at' => now()->addDay()->setTime(9, 0),
            'status' => 'paid',
        ]);
        $conflictingStart = now()->addDay()->setTime(11, 0);
        Appointment::create([
            'patient_id' => $otherPatient->id,
            'therapy_type_id' => $therapy->id,
            'therapist_profile_id' => $profile->id,
            'starts_at' => $conflictingStart,
            'ends_at' => $conflictingStart->copy()->addHour(),
            'status' => 'confirmed',
        ]);

        $this->actingAs($owner)->patch("/assessments/{$assessment->id}/schedule", [
            'scheduled_at' => $conflictingStart->copy()->addMinutes(30)->format('Y-m-d H:i:s'),
            'therapist_profile_id' => $profile->id,
        ])->assertSessionHasErrors('therapist_profile_id');

        $this->assertDatabaseHas('assessments', [
            'id' => $assessment->id,
            'scheduled_at' => $assessment->scheduled_at->format('Y-m-d H:i:s'),
            'therapist_profile_id' => $profile->id,
        ]);
    }

    public function test_therapy_schedule_rejects_an_overlapping_assessment_session(): void
    {
        $this->travelTo('2026-10-25 07:00:00');
        $owner = $this->userWithRole('owner');
        $patient = $this->patient();
        $otherPatient = Patient::create(['patient_code' => 'KL-2026-002', 'name' => 'Pasien Assessment', 'is_active' => true]);
        $therapist = User::factory()->create();
        $profile = TherapistProfile::create(['user_id' => $therapist->id]);
        $therapy = TherapyType::create(['code' => 'TW', 'name' => 'Terapi Wicara', 'default_duration_minutes' => 60]);
        $room = Room::create(['code' => 'R1', 'name' => 'Ruang 1']);
        $this->assignTherapist($patient, $therapy, $profile);
        $completedAssessment = Assessment::create(['patient_id' => $patient->id, 'status' => 'completed']);
        $assessmentStart = now()->addDay()->setTime(10, 0);
        Assessment::create([
            'patient_id' => $patient->id,
            'therapist_profile_id' => $profile->id,
            'scheduled_at' => $assessmentStart,
            'status' => 'awaiting_payment',
        ]);

        $this->actingAs($owner)->post('/scheduling', [
            'patient_id' => $patient->id,
            'assessment_id' => $completedAssessment->id,
            'therapy_type_id' => $therapy->id,
            'therapist_profile_id' => $profile->id,
            'room_id' => $room->id,
            'starts_at' => $assessmentStart->copy()->addMinutes(30)->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors('starts_at');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_patient_profile_can_be_updated_archived_and_restored(): void
    {
        $owner = $this->userWithRole('owner');
        $this->actingAs($owner);
        $patient = $this->patient();
        $sibling = Patient::create(['patient_code' => 'KL-2026-002', 'name' => 'Saudara Pasien', 'is_active' => true]);
        $father = ParentContact::create(['name' => 'Ayah Lama', 'whatsapp' => '081111111111']);
        $patient->parents()->attach($father->id, ['relation' => 'father', 'is_primary_contact' => true]);
        $sibling->parents()->attach($father->id, ['relation' => 'father', 'is_primary_contact' => true]);

        $this->put("/patients/{$patient->id}", [
            'name' => 'Pasien Diperbarui',
            'birth_date' => '2020-05-12',
            'gender' => 'female',
            'address' => 'Jl. Terapi No. 10',
            'primary_whatsapp' => '081234567890',
            'main_complaint' => 'Perlu terapi wicara',
            'school_name' => 'TK Bintang',
            'medical_history' => 'Tidak ada alergi yang diketahui.',
            'father_name' => 'Ayah Baru',
            'father_whatsapp' => '082222222222',
            'mother_name' => 'Ibu Baru',
            'mother_whatsapp' => '083333333333',
        ])->assertRedirect('/patients');

        $this->assertDatabaseHas('patients', [
            'id' => $patient->id,
            'name' => 'Pasien Diperbarui',
            'school_name' => 'TK Bintang',
            'medical_history' => 'Tidak ada alergi yang diketahui.',
        ]);
        $this->assertDatabaseHas('parents', ['name' => 'Ayah Baru', 'whatsapp' => '082222222222']);
        $this->assertDatabaseHas('parents', ['id' => $father->id, 'name' => 'Ayah Lama', 'whatsapp' => '081111111111']);
        $this->assertDatabaseHas('patient_parents', ['patient_id' => $sibling->id, 'parent_id' => $father->id]);
        $this->assertDatabaseHas('patient_parents', [
            'patient_id' => $patient->id,
            'relation' => 'mother',
            'is_primary_contact' => true,
        ]);

        $this->delete("/patients/{$patient->id}")->assertRedirect('/patients');
        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'is_active' => false]);
        $this->assertDatabaseHas('patient_parents', ['patient_id' => $patient->id]);

        $this->patch("/patients/{$patient->id}/restore")->assertRedirect('/patients');
        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'is_active' => true]);
    }

    public function test_patient_profile_saves_distinct_occupational_and_speech_therapists(): void
    {
        $this->actingAs($this->userWithRole('owner'));
        $patient = $this->patient();
        $occupationalType = TherapyType::create(['code' => 'TO', 'name' => 'Terapi Okupasi']);
        $speechType = TherapyType::create(['code' => 'TW', 'name' => 'Terapi Wicara']);
        $occupationalProfile = TherapistProfile::create(['user_id' => User::factory()->create()->id]);
        $speechProfile = TherapistProfile::create(['user_id' => User::factory()->create()->id]);

        $this->put("/patients/{$patient->id}", [
            'name' => $patient->name,
            'therapist_assignments' => ['TO' => $occupationalProfile->id, 'TW' => $occupationalProfile->id],
        ])->assertSessionHasErrors('therapist_assignments.TW');

        $this->put("/patients/{$patient->id}", [
            'name' => $patient->name,
            'therapist_assignments' => ['TO' => $occupationalProfile->id, 'TW' => $speechProfile->id],
        ])->assertRedirect('/patients');

        $this->assertDatabaseHas('patient_therapists', [
            'patient_id' => $patient->id,
            'therapy_type_id' => $occupationalType->id,
            'therapist_profile_id' => $occupationalProfile->id,
        ]);
        $this->assertDatabaseHas('patient_therapists', [
            'patient_id' => $patient->id,
            'therapy_type_id' => $speechType->id,
            'therapist_profile_id' => $speechProfile->id,
        ]);

        $this->get('/patients')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Patients/Index')
            ->where('patients.data.0.therapist_assignments.TO', $occupationalProfile->id)
            ->where('patients.data.0.therapist_assignments.TW', $speechProfile->id));
    }

    public function test_assessment_completion_saves_a_single_optional_therapist_assignment(): void
    {
        $this->actingAs($this->userWithRole('owner'));
        $patient = $this->patient();
        $speechType = TherapyType::create(['code' => 'TW', 'name' => 'Terapi Wicara']);
        TherapyType::create(['code' => 'TO', 'name' => 'Terapi Okupasi']);
        $speechProfile = TherapistProfile::create(['user_id' => User::factory()->create()->id]);
        $sale = Sale::create([
            'invoice_number' => 'INV-ASSESSMENT-THERAPIST',
            'patient_id' => $patient->id,
            'status' => 'paid',
            'subtotal' => 300000,
            'discount_total' => 0,
            'grand_total' => 300000,
            'paid_at' => now(),
        ]);
        $assessment = Assessment::create([
            'patient_id' => $patient->id,
            'sale_id' => $sale->id,
            'status' => 'paid',
            'scheduled_at' => now(),
        ]);

        $this->put(route('assessments.complete', $assessment), [
            'chief_complaint' => 'Perlu pendampingan terapi wicara.',
            'therapist_assignments' => ['TO' => '', 'TW' => $speechProfile->id],
        ])->assertRedirect('/assessments');

        $this->assertDatabaseHas('assessments', ['id' => $assessment->id, 'status' => 'completed']);
        $this->assertDatabaseHas('patient_therapists', [
            'patient_id' => $patient->id,
            'therapy_type_id' => $speechType->id,
            'therapist_profile_id' => $speechProfile->id,
        ]);
        $this->assertDatabaseCount('patient_therapists', 1);
        $this->get('/assessments')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Assessments/Index')
            ->where('assessments.data.0.patient.recommended_therapists.0.therapy_name', 'Terapi Wicara')
            ->where('assessments.data.0.patient.recommended_therapists.0.therapist_name', $speechProfile->user->name));
        $this->get(route('assessments.pdf', $assessment))->assertOk();
    }

    public function test_schedule_only_accepts_the_patient_therapist_recommended_for_speech_therapy(): void
    {
        $this->actingAs($this->userWithRole('owner'));
        $patient = $this->patient();
        $therapy = TherapyType::create(['code' => 'TW', 'name' => 'Terapi Wicara']);
        $this->sessionTariff($therapy);
        $assignedTherapist = TherapistProfile::create(['user_id' => User::factory()->create()->id]);
        $unassignedTherapist = TherapistProfile::create(['user_id' => User::factory()->create()->id]);
        $this->assignTherapist($patient, $therapy, $assignedTherapist);
        $assessment = Assessment::create(['patient_id' => $patient->id, 'status' => 'completed']);
        $room = Room::create(['code' => 'R1', 'name' => 'Ruang 1']);
        $payload = [
            'patient_id' => $patient->id,
            'assessment_id' => $assessment->id,
            'therapy_type_id' => $therapy->id,
            'room_id' => $room->id,
            'starts_at' => now()->addDays(2)->toDateTimeString(),
        ];

        $this->get('/scheduling')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Scheduling/Index')
            ->where('patientTherapists.0.therapist_profile_id', $assignedTherapist->id)
            ->where('patientTherapists.0.therapy_type_id', $therapy->id));

        $this->post('/scheduling', [...$payload, 'therapist_profile_id' => $unassignedTherapist->id])
            ->assertSessionHasErrors('therapist_profile_id');
        $this->assertDatabaseCount('appointments', 0);

        $this->post('/scheduling', [...$payload, 'therapist_profile_id' => $assignedTherapist->id])
            ->assertRedirect('/scheduling');
        $this->assertDatabaseHas('appointments', [
            'patient_id' => $patient->id,
            'therapy_type_id' => $therapy->id,
            'therapist_profile_id' => $assignedTherapist->id,
        ]);
    }

    public function test_staff_can_create_a_therapist_login_and_profile(): void
    {
        $owner = $this->userWithRole('owner');
        $this->actingAs($owner);

        $this->post('/therapists', [
            'name' => 'Dita Terapis',
            'email' => 'dita.terapis@example.test',
            'phone' => '081234567890',
            'specialty' => 'Terapi Okupasi',
            'license_number' => 'SIP-12345',
            'password' => 'Rahasia1234',
            'password_confirmation' => 'Rahasia1234',
        ])->assertRedirect('/therapists');

        $user = User::where('email', 'dita.terapis@example.test')->firstOrFail();

        $this->assertTrue(Hash::check('Rahasia1234', $user->password));
        $this->assertTrue($user->hasRole('therapist'));
        $this->assertDatabaseHas('therapist_profiles', [
            'user_id' => $user->id,
            'specialty' => 'Terapi Okupasi',
            'license_number' => 'SIP-12345',
        ]);
    }

    public function test_schedule_rejects_an_overlapping_therapist_booking(): void
    {
        $owner = $this->userWithRole('owner');
        $this->actingAs($owner);
        $patient = $this->patient();
        $otherPatient = Patient::create([
            'patient_code' => 'KL-2026-002',
            'name' => 'Pasien Uji Kedua',
            'is_active' => true,
        ]);
        $therapist = User::factory()->create();
        $profile = TherapistProfile::create(['user_id' => $therapist->id, 'specialty' => 'Wicara']);
        $therapy = TherapyType::create(['code' => 'TW', 'name' => 'Terapi Wicara', 'default_duration_minutes' => 60]);
        $this->assignTherapist($patient, $therapy, $profile);
        $this->sessionTariff($therapy);
        $assessment = Assessment::create(['patient_id' => $patient->id, 'status' => 'completed']);
        $roomOne = Room::create(['code' => 'R1', 'name' => 'Ruang 1']);
        $roomTwo = Room::create(['code' => 'R2', 'name' => 'Ruang 2']);
        $start = Carbon::now()->addDays(2)->startOfHour();
        $payload = [
            'patient_id' => $patient->id,
            'assessment_id' => $assessment->id,
            'therapy_type_id' => $therapy->id,
            'therapist_profile_id' => $profile->id,
            'room_id' => $roomOne->id,
            'starts_at' => $start->format('Y-m-d H:i:s'),
            'duration_minutes' => 60,
        ];

        $this->post('/scheduling', $payload)->assertRedirect('/scheduling');
        $this->post('/scheduling', [...$payload, 'room_id' => $roomTwo->id, 'starts_at' => $start->copy()->addMinutes(30)->format('Y-m-d H:i:s')])
            ->assertSessionHasErrors([
                'starts_at' => 'Jadwal bentrok: terapis ini sudah menangani anak lain pada waktu tersebut. Silakan pilih waktu atau terapis lain.',
            ]);
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_schedule_can_be_rescheduled_and_updated_without_overlapping_another_booking(): void
    {
        $this->actingAs($this->userWithRole('owner'));
        $patient = $this->patient();
        $otherPatient = Patient::create([
            'patient_code' => 'KL-2026-002',
            'name' => 'Pasien Uji Kedua',
            'is_active' => true,
        ]);
        $firstTherapist = User::factory()->create();
        $firstProfile = TherapistProfile::create(['user_id' => $firstTherapist->id, 'specialty' => 'Wicara']);
        $secondTherapist = User::factory()->create();
        $secondProfile = TherapistProfile::create(['user_id' => $secondTherapist->id, 'specialty' => 'Okupasi']);
        $thirdTherapist = User::factory()->create();
        $thirdProfile = TherapistProfile::create(['user_id' => $thirdTherapist->id, 'specialty' => 'Okupasi']);
        $therapy = TherapyType::create(['code' => 'TW', 'name' => 'Terapi Wicara', 'default_duration_minutes' => 30]);
        $otherTherapy = TherapyType::create(['code' => 'TO', 'name' => 'Terapi Okupasi', 'default_duration_minutes' => 45]);
        $this->sessionTariff($therapy);
        $this->sessionTariff($otherTherapy);
        $this->assignTherapist($patient, $therapy, $firstProfile);
        $this->assignTherapist($otherPatient, $therapy, $secondProfile);
        $this->assignTherapist($otherPatient, $otherTherapy, $thirdProfile);
        $firstAssessment = Assessment::create(['patient_id' => $patient->id, 'status' => 'completed']);
        $otherAssessment = Assessment::create(['patient_id' => $otherPatient->id, 'status' => 'completed']);
        $firstRoom = Room::create(['code' => 'R1', 'name' => 'Ruang 1']);
        $secondRoom = Room::create(['code' => 'R2', 'name' => 'Ruang 2']);
        $start = Carbon::now()->addDays(2)->startOfDay()->addHours(10);

        $this->post('/scheduling', [
            'patient_id' => $patient->id,
            'assessment_id' => $firstAssessment->id,
            'therapy_type_id' => $therapy->id,
            'therapist_profile_id' => $firstProfile->id,
            'room_id' => $firstRoom->id,
            'starts_at' => $start->toDateTimeString(),
        ])->assertRedirect('/scheduling');
        $this->post('/scheduling', [
            'patient_id' => $otherPatient->id,
            'assessment_id' => $otherAssessment->id,
            'therapy_type_id' => $therapy->id,
            'therapist_profile_id' => $secondProfile->id,
            'room_id' => $secondRoom->id,
            'starts_at' => $start->copy()->addHour()->toDateTimeString(),
        ])->assertRedirect('/scheduling');

        $appointment = Appointment::query()->where('patient_id', $patient->id)->firstOrFail();
        $this->put("/scheduling/{$appointment->id}", [
            'patient_id' => $otherPatient->id,
            'assessment_id' => $otherAssessment->id,
            'therapy_type_id' => $therapy->id,
            'therapist_profile_id' => $secondProfile->id,
            'room_id' => $secondRoom->id,
            'starts_at' => $start->copy()->addHour()->toDateTimeString(),
        ])->assertSessionHasErrors([
            'starts_at' => 'Jadwal bentrok: terapis ini sudah menangani anak lain pada waktu tersebut. Silakan pilih waktu atau terapis lain.',
        ]);

        $newStart = $start->copy()->addDays(2);
        $this->put("/scheduling/{$appointment->id}", [
            'patient_id' => $otherPatient->id,
            'assessment_id' => $otherAssessment->id,
            'therapy_type_id' => $otherTherapy->id,
            'therapist_profile_id' => $thirdProfile->id,
            'room_id' => $secondRoom->id,
            'starts_at' => $newStart->toDateTimeString(),
        ])->assertRedirect('/scheduling');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'patient_id' => $otherPatient->id,
            'therapy_type_id' => $otherTherapy->id,
            'therapist_profile_id' => $thirdProfile->id,
            'room_id' => $secondRoom->id,
            'starts_at' => $newStart->toDateTimeString(),
            'ends_at' => $newStart->copy()->addMinutes(45)->toDateTimeString(),
            'status' => 'scheduled',
        ]);
    }

    public function test_cancelling_a_schedule_restores_package_sessions_once(): void
    {
        $owner = $this->userWithRole('owner');
        $this->actingAs($owner);
        $patient = $this->patient();
        $therapist = User::factory()->create();
        $profile = TherapistProfile::create(['user_id' => $therapist->id, 'specialty' => 'Wicara']);
        $room = Room::create(['code' => 'R1', 'name' => 'Ruang 1']);
        $legacyTherapy = TherapyType::create(['code' => 'TW', 'name' => 'Terapi Wicara', 'default_duration_minutes' => 60]);
        $durationTherapy = TherapyType::create(['code' => 'TO', 'name' => 'Terapi Okupasi', 'default_duration_minutes' => 30]);
        $catalog = PackageCatalog::create([
            'name' => 'Paket pemulihan uji',
            'validity_days' => 90,
            'price' => 700000,
            'is_active' => true,
        ]);
        $patientPackage = PatientPackage::create([
            'patient_id' => $patient->id,
            'package_catalog_id' => $catalog->id,
            'status' => 'completed',
            'starts_at' => now()->subDays(5),
            'expires_at' => now()->addDays(85),
        ]);
        $legacyEntitlement = PackageEntitlement::create([
            'patient_package_id' => $patientPackage->id,
            'therapy_type_id' => $legacyTherapy->id,
            'granted_sessions' => 1,
            'remaining_sessions' => 0,
        ]);
        $durationEntitlementId = DB::table('duration_package_entitlements')->insertGetId([
            'patient_package_id' => $patientPackage->id,
            'duration_minutes' => 30,
            'granted_sessions' => 1,
            'remaining_sessions' => 0,
        ]);
        $legacyAppointment = Appointment::create([
            'patient_id' => $patient->id,
            'therapy_type_id' => $legacyTherapy->id,
            'therapist_profile_id' => $profile->id,
            'room_id' => $room->id,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDays(2)->addHour(),
            'status' => 'completed',
            'created_by' => $owner->id,
        ]);
        $durationAppointment = Appointment::create([
            'patient_id' => $patient->id,
            'therapy_type_id' => $durationTherapy->id,
            'therapist_profile_id' => $profile->id,
            'room_id' => $room->id,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->subDay()->addMinutes(30),
            'status' => 'completed',
            'created_by' => $owner->id,
        ]);
        $pendingAppointment = Appointment::create([
            'patient_id' => $patient->id,
            'therapy_type_id' => $legacyTherapy->id,
            'therapist_profile_id' => $profile->id,
            'room_id' => $room->id,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHour(),
            'status' => 'scheduled',
            'created_by' => $owner->id,
        ]);
        $legacyDebitId = DB::table('package_ledger')->insertGetId([
            'package_entitlement_id' => $legacyEntitlement->id,
            'appointment_id' => $legacyAppointment->id,
            'quantity_delta' => -1,
            'reason' => 'session_completed',
            'created_by' => $owner->id,
            'created_at' => now(),
        ]);
        $durationDebitId = DB::table('duration_package_ledger')->insertGetId([
            'duration_package_entitlement_id' => $durationEntitlementId,
            'appointment_id' => $durationAppointment->id,
            'quantity_delta' => -1,
            'reason' => 'session_completed',
            'created_by' => $owner->id,
            'created_at' => now(),
        ]);

        $this->patch("/scheduling/{$legacyAppointment->id}/cancel")->assertRedirect('/scheduling');
        $this->patch("/scheduling/{$durationAppointment->id}/cancel")->assertRedirect('/scheduling');
        $this->patch("/scheduling/{$pendingAppointment->id}/cancel")->assertRedirect('/scheduling');
        $this->patch("/scheduling/{$legacyAppointment->id}/cancel")->assertRedirect('/scheduling');

        $this->assertDatabaseHas('appointments', ['id' => $legacyAppointment->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('appointments', ['id' => $durationAppointment->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('appointments', ['id' => $pendingAppointment->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('package_entitlements', ['id' => $legacyEntitlement->id, 'remaining_sessions' => 1]);
        $this->assertDatabaseHas('duration_package_entitlements', ['id' => $durationEntitlementId, 'remaining_sessions' => 1]);
        $this->assertDatabaseHas('patient_packages', ['id' => $patientPackage->id, 'status' => 'active']);
        $this->assertDatabaseHas('package_ledger', ['reversal_of_id' => $legacyDebitId, 'quantity_delta' => 1, 'reason' => 'appointment_cancelled']);
        $this->assertDatabaseHas('duration_package_ledger', ['reversal_of_id' => $durationDebitId, 'quantity_delta' => 1, 'reason' => 'appointment_cancelled']);
        $this->assertDatabaseCount('package_ledger', 2);
        $this->assertDatabaseCount('duration_package_ledger', 2);
    }

    public function test_a_therapist_cannot_be_assigned_to_multiple_children(): void
    {
        $owner = $this->userWithRole('owner');
        $this->actingAs($owner);
        $patient = $this->patient();
        $otherPatient = Patient::create(['patient_code' => 'KL-2026-002', 'name' => 'Pasien Kedua', 'is_active' => true]);
        $therapist = User::factory()->create();
        $profile = TherapistProfile::create(['user_id' => $therapist->id, 'specialty' => 'Wicara']);
        $therapy = TherapyType::create(['code' => 'TW', 'name' => 'Terapi Wicara', 'default_duration_minutes' => 60]);
        $this->assignTherapist($patient, $therapy, $profile);

        $this->put("/patients/{$otherPatient->id}", [
            'name' => $otherPatient->name,
            'therapist_assignments' => ['TO' => '', 'TW' => $profile->id],
        ])->assertSessionHasErrors('therapist_assignments.TW');

        $this->assertDatabaseHas('patient_therapists', [
            'patient_id' => $patient->id,
            'therapy_type_id' => $therapy->id,
            'therapist_profile_id' => $profile->id,
        ]);
        $this->assertDatabaseCount('patient_therapists', 1);
    }

    public function test_scheduling_uses_the_selected_session_duration(): void
    {
        $owner = $this->userWithRole('owner');
        $this->actingAs($owner);
        $patient = $this->patient();
        $therapist = User::factory()->create();
        $profile = TherapistProfile::create(['user_id' => $therapist->id, 'specialty' => 'Wicara']);
        $therapy = TherapyType::create(['code' => 'TW', 'name' => 'Terapi Wicara', 'default_duration_minutes' => 60]);
        $this->assignTherapist($patient, $therapy, $profile);
        $this->sessionTariff($therapy);
        $assessment = Assessment::create(['patient_id' => $patient->id, 'status' => 'completed']);
        $room = Room::create(['code' => 'R1', 'name' => 'Ruang 1']);
        $start = Carbon::now()->addDays(2)->startOfHour();

        $this->post('/scheduling', [
            'patient_id' => $patient->id,
            'assessment_id' => $assessment->id,
            'therapy_type_id' => $therapy->id,
            'therapist_profile_id' => $profile->id,
            'room_id' => $room->id,
            'starts_at' => $start->format('Y-m-d H:i:s'),
            'duration_minutes' => 30,
        ])->assertRedirect('/scheduling');

        $appointment = Appointment::firstOrFail();
        $this->assertSame(30, (int) $appointment->starts_at->diffInMinutes($appointment->ends_at));
    }

    public function test_seeded_service_and_package_prices_match_the_clinic_tariff(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('service_catalog', [
            'code' => 'ASSESSMENT-AWAL',
            'price' => 300000,
            'description' => 'Wajib dilakukan pada pertemuan awal sebelum sesi terapi.',
        ]);
        $this->assertDatabaseHas('service_catalog', [
            'code' => 'EVALUASI-24-SESI',
            'price' => 250000,
            'description' => 'Dilakukan setelah 24 sesi terapi.',
        ]);

        foreach ([
            [60, 1, 160000, 160000], [60, 4, 630000, 157500], [60, 8, 1240000, 155000], [60, 12, 1800000, 150000],
            [30, 1, 110000, 110000], [30, 6, 570000, 95000], [30, 12, 1080000, 90000], [30, 16, 1360000, 85000],
        ] as [$duration, $sessions, $price, $pricePerSession]) {
            $this->assertDatabaseHas('package_catalog', [
                'session_duration_minutes' => $duration,
                'session_quantity' => $sessions,
                'price' => $price,
                'is_active' => true,
            ]);
            $this->assertSame($pricePerSession, (int) ($price / $sessions));
        }
    }

    public function test_package_checkout_records_full_payment_and_activates_entitlements(): void
    {
        $cashier = $this->userWithRole('finance');
        $this->actingAs($cashier);
        $patient = $this->patient();
        $therapist = User::factory()->create();
        $therapistProfile = TherapistProfile::create(['user_id' => $therapist->id, 'specialty' => 'Wicara']);
        $therapy = TherapyType::create(['code' => 'TW', 'name' => 'Terapi Wicara', 'default_duration_minutes' => 60]);
        $packageId = DB::table('package_catalog')->insertGetId([
            'name' => 'Paket 4 sesi uji', 'validity_days' => 90, 'price' => 700000, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('package_catalog_items')->insert([
            'package_catalog_id' => $packageId,
            'therapy_type_id' => $therapy->id,
            'session_quantity' => 4,
        ]);

        $response = $this->post('/sales', [
            'patient_id' => $patient->id,
            'therapist_profile_id' => $therapistProfile->id,
            'line_type' => 'package',
            'item_id' => $packageId,
            'quantity' => 1,
            'discount_type' => 'percent',
            'discount_value' => 10,
            'payment_method' => 'qris',
        ]);

        $response->assertRedirect('/sales/1/receipt');
        $this->assertDatabaseHas('sales', [
            'grand_total' => 630000,
            'status' => 'paid',
            'therapist_profile_id' => $therapistProfile->id,
        ]);
        $this->assertDatabaseHas('payments', ['amount' => 630000, 'method' => 'qris']);
        $this->assertDatabaseHas('patient_packages', ['patient_id' => $patient->id, 'status' => 'active']);
        $this->assertDatabaseHas('package_entitlements', ['therapy_type_id' => $therapy->id, 'granted_sessions' => 4, 'remaining_sessions' => 4]);
    }

    public function test_duration_package_checkout_calculates_quantity_and_grants_all_sessions(): void
    {
        $cashier = $this->userWithRole('finance');
        $this->actingAs($cashier);
        $patient = $this->patient();
        $package = PackageCatalog::create([
            'name' => 'Paket 1 Jam - Paket 3 (8x Terapi)',
            'validity_days' => 90,
            'price' => 1240000,
            'session_duration_minutes' => 60,
            'session_quantity' => 8,
            'is_active' => true,
        ]);

        $this->post('/sales', [
            'patient_id' => $patient->id,
            'line_type' => 'package',
            'item_id' => $package->id,
            'quantity' => 2,
            'discount_type' => 'percent',
            'discount_value' => 10,
            'payment_method' => 'qris',
        ])->assertRedirect('/sales/1/receipt');

        $this->assertDatabaseHas('sales', ['grand_total' => 2232000, 'subtotal' => 2480000, 'discount_total' => 248000]);
        $this->assertDatabaseHas('duration_package_entitlements', [
            'duration_minutes' => 60,
            'granted_sessions' => 16,
            'remaining_sessions' => 16,
        ]);
        $this->assertDatabaseHas('duration_package_ledger', ['quantity_delta' => 16, 'reason' => 'package_purchase']);
    }

    public function test_therapist_sees_only_sessions_assigned_to_their_profile(): void
    {
        $therapist = $this->userWithRole('therapist');
        $otherTherapist = $this->userWithRole('therapist');
        $therapistProfile = TherapistProfile::create(['user_id' => $therapist->id]);
        $otherTherapistProfile = TherapistProfile::create(['user_id' => $otherTherapist->id]);
        $therapy = TherapyType::create(['code' => 'TO', 'name' => 'Terapi Okupasi', 'default_duration_minutes' => 60]);
        $assignedPatient = $this->patient();
        $otherPatient = Patient::create(['patient_code' => 'KL-2026-002', 'name' => 'Pasien Lain', 'is_active' => true]);
        $unassignedPatient = Patient::create(['patient_code' => 'KL-2026-003', 'name' => 'Pasien Belum Ditugaskan', 'is_active' => true]);
        $assignedAppointment = Appointment::create([
            'patient_id' => $assignedPatient->id,
            'therapy_type_id' => $therapy->id,
            'therapist_profile_id' => $therapistProfile->id,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'status' => 'in_progress',
        ]);
        Appointment::create([
            'patient_id' => $otherPatient->id,
            'therapy_type_id' => $therapy->id,
            'therapist_profile_id' => $otherTherapistProfile->id,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'status' => 'in_progress',
        ]);
        Appointment::create([
            'patient_id' => $unassignedPatient->id,
            'therapy_type_id' => $therapy->id,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'status' => 'in_progress',
        ]);

        $this->actingAs($therapist)->get('/sessions')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sessions/Index')
                ->has('appointments', 1)
                ->where('appointments.0.id', $assignedAppointment->id)
                ->where('appointments.0.patient.name', $assignedPatient->name));
    }

    public function test_owner_can_see_sessions_assigned_to_all_therapists(): void
    {
        $owner = $this->userWithRole('owner');
        $firstTherapist = User::factory()->create();
        $secondTherapist = User::factory()->create();
        $firstProfile = TherapistProfile::create(['user_id' => $firstTherapist->id]);
        $secondProfile = TherapistProfile::create(['user_id' => $secondTherapist->id]);
        $therapy = TherapyType::create(['code' => 'TO', 'name' => 'Terapi Okupasi', 'default_duration_minutes' => 60]);

        foreach ([[$firstProfile, $this->patient()], [$secondProfile, Patient::create([
            'patient_code' => 'KL-2026-002',
            'name' => 'Pasien Lain',
            'is_active' => true,
        ])]] as [$profile, $patient]) {
            Appointment::create([
                'patient_id' => $patient->id,
                'therapy_type_id' => $therapy->id,
                'therapist_profile_id' => $profile->id,
                'starts_at' => now()->subHour(),
                'ends_at' => now()->addHour(),
                'status' => 'in_progress',
            ]);
        }

        $this->actingAs($owner)->get('/sessions')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sessions/Index')
                ->has('appointments', 2));
    }

    public function test_therapist_cannot_submit_a_report_for_another_therapists_session(): void
    {
        $therapist = $this->userWithRole('therapist');
        $assignedTherapist = User::factory()->create();
        $assignedProfile = TherapistProfile::create(['user_id' => $assignedTherapist->id]);
        $patient = $this->patient();
        $therapy = TherapyType::create(['code' => 'TO', 'name' => 'Terapi Okupasi', 'default_duration_minutes' => 60]);
        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'therapy_type_id' => $therapy->id,
            'therapist_profile_id' => $assignedProfile->id,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'status' => 'in_progress',
        ]);

        $this->actingAs($therapist)
            ->post("/sessions/{$appointment->id}/report", ['activities' => 'Laporan yang tidak berhak dikirim.'])
            ->assertForbidden();

        $this->assertDatabaseCount('session_reports', 0);
    }

    public function test_submitting_a_session_report_consumes_one_package_session_once(): void
    {
        $therapist = $this->userWithRole('therapist');
        $this->actingAs($therapist);
        $patient = $this->patient();
        $therapy = TherapyType::create(['code' => 'TO', 'name' => 'Terapi Okupasi', 'default_duration_minutes' => 60]);
        $room = Room::create(['code' => 'TO-1', 'name' => 'Ruang Okupasi']);
        $profile = TherapistProfile::create(['user_id' => $therapist->id]);
        $packageCatalogId = DB::table('package_catalog')->insertGetId([
            'name' => 'Paket uji', 'validity_days' => 30, 'price' => 800000, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $patientPackage = PatientPackage::create([
            'patient_id' => $patient->id,
            'package_catalog_id' => $packageCatalogId,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(29),
        ]);
        $entitlement = PackageEntitlement::create([
            'patient_package_id' => $patientPackage->id,
            'therapy_type_id' => $therapy->id,
            'granted_sessions' => 4,
            'remaining_sessions' => 4,
        ]);
        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'therapy_type_id' => $therapy->id,
            'therapist_profile_id' => $profile->id,
            'room_id' => $room->id,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'status' => 'in_progress',
        ]);
        $reportData = ['activities' => 'Latihan artikulasi dan koordinasi motorik oral.'];

        $this->post("/sessions/{$appointment->id}/report", $reportData)->assertRedirect('/sessions');
        $this->assertDatabaseHas('package_entitlements', ['id' => $entitlement->id, 'remaining_sessions' => 3]);
        $this->assertDatabaseHas('package_ledger', ['appointment_id' => $appointment->id, 'quantity_delta' => -1]);

        $this->post("/sessions/{$appointment->id}/report", $reportData)->assertSessionHasErrors('activities');
        $this->assertDatabaseHas('package_entitlements', ['id' => $entitlement->id, 'remaining_sessions' => 3]);
        $this->assertDatabaseCount('package_ledger', 1);
    }

    public function test_session_report_consumes_the_entitlement_matching_appointment_duration(): void
    {
        $therapist = $this->userWithRole('therapist');
        $this->actingAs($therapist);
        $patient = $this->patient();
        $therapy = TherapyType::create(['code' => 'TO', 'name' => 'Terapi Okupasi', 'default_duration_minutes' => 60]);
        $room = Room::create(['code' => 'TO-1', 'name' => 'Ruang Okupasi']);
        $profile = TherapistProfile::create(['user_id' => $therapist->id]);
        $packageCatalogId = DB::table('package_catalog')->insertGetId([
            'name' => 'Paket durasi uji', 'validity_days' => 30, 'price' => 570000,
            'session_duration_minutes' => 30, 'session_quantity' => 6, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $patientPackage = PatientPackage::create([
            'patient_id' => $patient->id,
            'package_catalog_id' => $packageCatalogId,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(29),
        ]);
        $entitlementId = DB::table('duration_package_entitlements')->insertGetId([
            'patient_package_id' => $patientPackage->id,
            'duration_minutes' => 30,
            'granted_sessions' => 6,
            'remaining_sessions' => 6,
        ]);
        DB::table('duration_package_entitlements')->insert([
            'patient_package_id' => $patientPackage->id,
            'duration_minutes' => 60,
            'granted_sessions' => 8,
            'remaining_sessions' => 8,
        ]);
        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'therapy_type_id' => $therapy->id,
            'therapist_profile_id' => $profile->id,
            'room_id' => $room->id,
            'starts_at' => now()->subMinutes(45),
            'ends_at' => now()->subMinutes(15),
            'status' => 'in_progress',
        ]);

        $this->post("/sessions/{$appointment->id}/report", [
            'activities' => 'Latihan artikulasi dan koordinasi motorik oral.',
        ])->assertRedirect('/sessions');

        $this->assertDatabaseHas('duration_package_entitlements', ['id' => $entitlementId, 'remaining_sessions' => 5]);
        $this->assertDatabaseHas('duration_package_entitlements', ['duration_minutes' => 60, 'remaining_sessions' => 8]);
        $this->assertDatabaseHas('duration_package_ledger', [
            'duration_package_entitlement_id' => $entitlementId,
            'appointment_id' => $appointment->id,
            'quantity_delta' => -1,
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $roleRecord = Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user->assignRole($roleRecord);

        return $user;
    }

    private function patient(): Patient
    {
        return Patient::create([
            'patient_code' => 'KL-2026-001',
            'name' => 'Pasien Uji',
            'is_active' => true,
        ]);
    }

    private function assignTherapist(Patient $patient, TherapyType $therapy, TherapistProfile $therapist): void
    {
        $patient->therapists()->attach($therapist->id, ['therapy_type_id' => $therapy->id]);
    }

    private function sessionTariff(TherapyType $therapy): int
    {
        return DB::table('service_catalog')->insertGetId([
            'code' => "SESSION-{$therapy->id}",
            'name' => $therapy->name,
            'service_type' => 'session',
            'therapy_type_id' => $therapy->id,
            'price' => 160000,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assessmentService(): int
    {
        return DB::table('service_catalog')->insertGetId([
            'code' => 'ASSESSMENT-TEST',
            'name' => 'Assessment uji',
            'service_type' => 'assessment',
            'price' => 300000,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
