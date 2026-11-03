<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_history_filters_by_day_therapist_and_payment_method(): void
    {
        $cashier = $this->userWithRole('finance');
        $this->actingAs($cashier);
        $patient = $this->patient();
        $therapist = $this->therapist('Terapis Satu');
        $otherTherapist = $this->therapist('Terapis Dua');

        $this->createPayment($cashier, $patient, $therapist, 'INV-HARI-CASH', '2026-10-18 17:30:00', 'cash', 500000);
        $this->createPayment($cashier, $patient, $therapist, 'INV-HARI-QRIS', '2026-10-19 04:30:00', 'qris', 250000);
        $this->createPayment($cashier, $patient, $otherTherapist, 'INV-LAIN-TERAPIS', '2026-10-18 17:30:00', 'cash', 100000);
        $this->createPayment($cashier, $patient, $therapist, 'INV-LAIN-HARI', '2026-10-19 18:00:00', 'cash', 75000);

        $this->get('/payment-history?period=daily&date=2026-10-19&therapist_id='.$therapist->id.'&method=cash')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Payments/History')
                ->where('summary.count', 1)
                ->where('summary.amount', 500000)
                ->where('payments.data.0.sale.invoice_number', 'INV-HARI-CASH')
                ->where('payments.data.0.sale.therapist_profile_id', $therapist->id));
    }

    public function test_payment_history_applies_monday_to_sunday_week_and_indonesia_month_ranges(): void
    {
        $cashier = $this->userWithRole('finance');
        $this->actingAs($cashier);
        $patient = $this->patient();
        $therapist = $this->therapist('Terapis Satu');

        $this->createPayment($cashier, $patient, $therapist, 'INV-MON', '2026-10-18 17:30:00', 'cash', 100000);
        $this->createPayment($cashier, $patient, $therapist, 'INV-SUN', '2026-10-25 16:30:00', 'cash', 200000);
        $this->createPayment($cashier, $patient, $therapist, 'INV-NEXT-WEEK', '2026-10-25 17:30:00', 'cash', 300000);

        $this->get('/payment-history?period=weekly&date=2026-10-21')
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.count', 2)
                ->where('summary.amount', 300000));

        $this->get('/payment-history?period=monthly&month=2026-10')
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.count', 3)
                ->where('summary.amount', 600000));
    }

    public function test_csv_export_uses_the_selected_filters(): void
    {
        $cashier = $this->userWithRole('finance');
        $this->actingAs($cashier);
        $patient = $this->patient();
        $therapist = $this->therapist('Terapis Satu');
        $otherTherapist = $this->therapist('Terapis Dua');

        $this->createPayment($cashier, $patient, $therapist, 'INV-CSV-OK', '2026-10-18 17:30:00', 'qris', 500000);
        $this->createPayment($cashier, $patient, $otherTherapist, 'INV-CSV-OTHER', '2026-10-18 17:30:00', 'qris', 100000);
        $this->createPayment($cashier, $patient, $therapist, 'INV-CSV-METHOD', '2026-10-18 17:30:00', 'cash', 200000);

        $response = $this->get('/payment-history/export?period=daily&date=2026-10-19&therapist_id='.$therapist->id.'&method=qris');

        $response->assertDownload('riwayat-pembayaran-daily-20261019.csv');
        $this->assertStringContainsString('INV-CSV-OK', $response->streamedContent());
        $this->assertStringNotContainsString('INV-CSV-OTHER', $response->streamedContent());
        $this->assertStringNotContainsString('INV-CSV-METHOD', $response->streamedContent());
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

    private function therapist(string $name): TherapistProfile
    {
        $user = User::factory()->create(['name' => $name]);

        return TherapistProfile::create(['user_id' => $user->id]);
    }

    private function createPayment(
        User $cashier,
        Patient $patient,
        TherapistProfile $therapist,
        string $invoice,
        string $receivedAt,
        string $method,
        int $amount,
    ): Payment {
        $sale = Sale::create([
            'invoice_number' => $invoice,
            'patient_id' => $patient->id,
            'cashier_id' => $cashier->id,
            'therapist_profile_id' => $therapist->id,
            'status' => 'paid',
            'subtotal' => $amount,
            'discount_total' => 0,
            'grand_total' => $amount,
            'paid_at' => $receivedAt,
        ]);

        return Payment::create([
            'sale_id' => $sale->id,
            'method' => $method,
            'amount' => $amount,
            'received_by' => $cashier->id,
            'received_at' => $receivedAt,
        ]);
    }
}
