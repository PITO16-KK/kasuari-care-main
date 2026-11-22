<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DummyPatientSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_pages_load_successfully(): void
    {
        $this->seed(DummyPatientSeeder::class);

        $owner = User::where('email', 'owner@rumahterapi.test')->firstOrFail();
        $this->actingAs($owner);

        $urls = [
            '/dashboard',
            '/assessments',
            '/sessions',
            '/bills',
            '/payment-history',
            '/finance/records',
        ];

        foreach ($urls as $url) {
            $response = $this->get($url);
            echo "\nTesting URL: $url -> Status: ".$response->status();
            $response->assertOk();
        }
    }

    public function test_dashboard_after_new_patient_registration(): void
    {
        $this->seed(DummyPatientSeeder::class);

        $admin = User::where('email', 'admin@rumahterapi.test')->firstOrFail();
        $this->actingAs($admin);

        $therapist = \App\Models\TherapistProfile::firstOrFail();

        // Pasien baru dengan rekomendasi terapis yang sudah menangani pasien lain
        $response = $this->post('/patients', [
            'name' => 'Anak Baru Uji',
            'birth_date' => '2020-05-10',
            'gender' => 'male',
            'address' => 'Jl. Baru No. 1',
            'primary_whatsapp' => '08987654321',
            'main_complaint' => 'Keluhan baru',
            'father_name' => 'Budi Baru',
            'father_whatsapp' => '08987654321',
            'therapist_assignments' => [
                'TW' => $therapist->id,
                'OT' => '',
                'FT' => '',
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/patients');

        $newPatient = \App\Models\Patient::where('name', 'Anak Baru Uji')->firstOrFail();
        $service = \Illuminate\Support\Facades\DB::table('service_catalog')->where('code', 'ASSESSMENT-AWAL')->first();
        $therapist = \App\Models\TherapistProfile::first();

        $assessmentResponse = $this->post('/assessments', [
            'patient_id' => $newPatient->id,
            'scheduled_at' => now()->addHours(2)->format('Y-m-d H:i:s'),
            'therapist_profile_id' => $therapist->id,
            'service_catalog_id' => $service->id,
        ]);
        $assessmentResponse->assertSessionHasNoErrors();

        $room = \App\Models\Room::first();
        $therapyType = \App\Models\TherapyType::where('is_active', true)->first();
        $appointmentResponse = $this->post('/scheduling', [
            'patient_id' => $newPatient->id,
            'therapist_profile_id' => $therapist->id,
            'room_id' => $room->id,
            'therapy_type_id' => $therapyType->id,
            'starts_at' => now()->addHours(4)->format('Y-m-d H:i:s'),
            'duration_minutes' => 60,
        ]);
        echo "\nScheduling status: " . $appointmentResponse->status();

        // Cek dashboard untuk admin/front_office
        $dashAdmin = $this->get('/dashboard');
        $dashAdmin->assertOk();

        // Cek dashboard untuk owner
        $owner = User::where('email', 'owner@rumahterapi.test')->firstOrFail();
        $dashOwner = $this->actingAs($owner)->get('/dashboard');
        $dashOwner->assertOk();

        // Cek dashboard untuk terapis
        $therapistUser = User::where('email', 'terapis@rumahterapi.test')->firstOrFail();
        $dashTherapist = $this->actingAs($therapistUser)->get('/dashboard');
        $dashTherapist->assertOk();
    }
}
