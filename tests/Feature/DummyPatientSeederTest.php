<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Assessment;
use App\Models\Patient;
use App\Models\PatientPackage;
use Database\Seeders\DummyPatientSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DummyPatientSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_dummy_patient_seeder_creates_expected_data_structure(): void
    {
        $this->seed(DummyPatientSeeder::class);

        // 1. Terdapat 8 pasien dummy
        $expectedPatients = [
            'Kenzo Alvaro',
            'Aisha Humaira',
            'Rayyan Arkanza',
            'Mikayla Zahra',
            'Rafka Daniswara',
            'Arka Bimasena',
            'Naura Salsabila',
            'Gibran Al-Fatih',
        ];

        foreach ($expectedPatients as $name) {
            $this->assertDatabaseHas('patients', ['name' => $name]);
        }

        // 2. 5 Anak Pasien dengan Paket Terapi Berbeda-beda
        $activeFiveConfigs = [
            'Kenzo Alvaro' => ['package' => 'Paket 1 Jam - Paket 2 (4x Terapi)', 'granted' => 4, 'remaining' => 3, 'completed' => 1],
            'Aisha Humaira' => ['package' => 'Paket 1 Jam - Paket 3 (8x Terapi)', 'granted' => 8, 'remaining' => 6, 'completed' => 2],
            'Rayyan Arkanza' => ['package' => 'Paket 1 Jam - Paket 4 (12x Terapi)', 'granted' => 12, 'remaining' => 8, 'completed' => 4],
            'Mikayla Zahra' => ['package' => 'Paket 30 Menit - Paket 2 (6x Terapi)', 'granted' => 6, 'remaining' => 4, 'completed' => 2],
            'Rafka Daniswara' => ['package' => 'Paket 30 Menit - Paket 4 (16x Terapi)', 'granted' => 16, 'remaining' => 11, 'completed' => 5],
        ];

        $packageNames = [];
        foreach ($activeFiveConfigs as $patientName => $config) {
            $patient = Patient::where('name', $patientName)->firstOrFail();
            $packageNames[] = $config['package'];

            // Cek paket aktif
            $patientPackage = PatientPackage::where('patient_id', $patient->id)->where('status', 'active')->first();
            $this->assertNotNull($patientPackage, "Pasien {$patientName} harus memiliki paket aktif.");
            $this->assertSame($config['package'], $patientPackage->catalog->name);

            // Cek durasi entitlement dan sisa kuota
            $entitlement = DB::table('duration_package_entitlements')->where('patient_package_id', $patientPackage->id)->first();
            $this->assertNotNull($entitlement);
            $this->assertSame($config['granted'], (int) $entitlement->granted_sessions);
            $this->assertSame($config['remaining'], (int) $entitlement->remaining_sessions);

            // Cek jumlah sesi yang sudah diselesaikan
            $completedAppointmentsCount = Appointment::where('patient_id', $patient->id)->where('status', 'completed')->count();
            $this->assertSame($config['completed'], $completedAppointmentsCount);

            // Cek assessment selesai
            $this->assertTrue(Assessment::where('patient_id', $patient->id)->where('status', 'completed')->exists());
        }

        // Pastikan semua 5 paket berbeda satu sama lain
        $this->assertCount(5, array_unique($packageNames));

        // 3. 2 Anak yang Belum Assessment
        // Anak 6: Arka Bimasena - belum ada assessment sama sekali
        $arka = Patient::where('name', 'Arka Bimasena')->firstOrFail();
        $this->assertFalse(Assessment::where('patient_id', $arka->id)->exists());
        $this->assertFalse(PatientPackage::where('patient_id', $arka->id)->exists());
        $this->assertDatabaseHas('patient_intake_submissions', ['patient_id' => $arka->id, 'verification_status' => 'pending']);

        // Anak 7: Naura Salsabila - assessment terjadwal tapi belum dilaksanakan
        $naura = Patient::where('name', 'Naura Salsabila')->firstOrFail();
        $nauraAssessment = Assessment::where('patient_id', $naura->id)->firstOrFail();
        $this->assertSame('scheduled', $nauraAssessment->status);
        $this->assertNull($nauraAssessment->completed_at);
        $this->assertFalse(PatientPackage::where('patient_id', $naura->id)->exists());

        // 4. 1 Anak yang Sudah Menjalankan Terapi 12x dan Paketnya Habis 0
        $gibran = Patient::where('name', 'Gibran Al-Fatih')->firstOrFail();
        $gibranPackage = PatientPackage::where('patient_id', $gibran->id)->firstOrFail();
        $this->assertSame('completed', $gibranPackage->status);

        $gibranEntitlement = DB::table('duration_package_entitlements')->where('patient_package_id', $gibranPackage->id)->firstOrFail();
        $this->assertSame(12, (int) $gibranEntitlement->granted_sessions);
        $this->assertSame(0, (int) $gibranEntitlement->remaining_sessions);

        // Tepat 12 appointment berstatus completed dan attendance present
        $completedGibranAppointments = Appointment::where('patient_id', $gibran->id)
            ->where('status', 'completed')
            ->where('attendance_status', 'present')
            ->get();
        $this->assertCount(12, $completedGibranAppointments);

        // Tepat 12 session reports dengan status submitted
        $sessionReportsCount = DB::table('session_reports')
            ->whereIn('appointment_id', $completedGibranAppointments->pluck('id'))
            ->where('status', 'submitted')
            ->count();
        $this->assertSame(12, $sessionReportsCount);

        // Tepat 12 debit di duration_package_ledger
        $debitLedgerCount = DB::table('duration_package_ledger')
            ->where('duration_package_entitlement_id', $gibranEntitlement->id)
            ->where('quantity_delta', -1)
            ->where('reason', 'session_completed')
            ->count();
        $this->assertSame(12, $debitLedgerCount);
    }
}
