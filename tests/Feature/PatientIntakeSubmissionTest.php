<?php

namespace Tests\Feature;

use App\Models\ParentContact;
use App\Models\Patient;
use App\Models\PatientIntakeSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PatientIntakeSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'test-google-form-webhook-secret-0123456789';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google_forms.webhook_secret' => self::WEBHOOK_SECRET]);
    }

    public function test_signed_webhook_stores_empty_answer_groups_and_accepts_nullable_submission_time(): void
    {
        $response = $this->postSignedWebhook([
            'source' => 'google_forms',
            'response_id' => 'form-response-empty-001',
            'submitted_at' => null,
        ]);

        $response->assertAccepted()->assertJsonPath('status', 'accepted');

        $submission = PatientIntakeSubmission::query()->where('response_id', 'form-response-empty-001')->firstOrFail();
        $this->assertNull($submission->submitted_at);
        $this->assertNull($submission->patient_id);
        $this->assertNull($submission->child_data);
        $this->assertNull($submission->father_data);
        $this->assertNull($submission->mother_data);
        $this->assertSame('pending', $submission->verification_status);
    }

    public function test_signed_webhook_stores_structured_answers_and_deduplicates_by_response_id(): void
    {
        $payload = [
            'source' => 'google_forms',
            'response_id' => 'form-response-unique-002',
            'submitted_at' => '2026-11-02T08:30:00.000Z',
            'child_data' => ['child_name' => 'Anak Uji', 'birth_date' => '2021-05-12'],
            'mother_data' => ['contact_details' => 'Ibu Uji, 33 tahun, SMA, 081234567890'],
            'development_history' => ['feeding' => 'Memilih tekstur tertentu'],
        ];

        $this->postSignedWebhook($payload)->assertAccepted();
        $duplicateResponse = $this->postSignedWebhook($payload);

        $duplicateResponse->assertOk()->assertJsonPath('status', 'duplicate');
        $this->assertSame(1, PatientIntakeSubmission::query()->where('response_id', 'form-response-unique-002')->count());
        $this->assertSame('Anak Uji', PatientIntakeSubmission::query()->where('response_id', 'form-response-unique-002')->firstOrFail()->child_data['child_name']);
    }

    public function test_webhook_returns_401_for_an_invalid_signature_without_saving_data(): void
    {
        $response = $this->postSignedWebhook([
            'source' => 'google_forms',
            'response_id' => 'form-response-invalid-signature',
        ], 'wrong-secret');

        $response->assertUnauthorized();
        $this->assertDatabaseCount('patient_intake_submissions', 0);
    }

    public function test_webhook_returns_401_for_a_stale_signature(): void
    {
        $response = $this->postSignedWebhook([
            'source' => 'google_forms',
            'response_id' => 'form-response-stale-signature',
        ], self::WEBHOOK_SECRET, (string) now()->subMinutes(6)->timestamp);

        $response->assertUnauthorized();
        $this->assertDatabaseCount('patient_intake_submissions', 0);
    }

    public function test_webhook_returns_422_for_unknown_answer_keys(): void
    {
        $response = $this->postSignedWebhook([
            'source' => 'google_forms',
            'response_id' => 'form-response-unknown-field',
            'child_data' => ['unexpected_field' => 'ignore'],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['child_data']);
        $this->assertDatabaseCount('patient_intake_submissions', 0);
    }

    public function test_therapists_can_view_intakes_and_finance_users_cannot(): void
    {
        $submission = PatientIntakeSubmission::factory()->create([
            'response_id' => 'form-response-view-access',
            'child_data' => ['child_name' => 'Anak Terlihat'],
            'development_history' => ['feeding' => 'Catatan makan'],
        ]);

        $therapist = $this->userWithRole('therapist');
        $this->actingAs($therapist)->get(route('patient-intakes.show', $submission))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PatientIntakes/Show')
                ->where('submission.response_id', 'form-response-view-access')
                ->where('submission.development_history.feeding', 'Catatan makan'));

        $financeUser = $this->userWithRole('finance');
        $this->actingAs($financeUser)->get(route('patient-intakes.show', $submission))->assertForbidden();
    }

    public function test_front_office_can_link_an_intake_to_a_patient_by_patient_code(): void
    {
        $submission = PatientIntakeSubmission::factory()->create(['response_id' => 'form-response-link']);
        $patient = Patient::create([
            'patient_code' => 'KL-2026-044',
            'name' => 'Nama Sama',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->userWithRole('front_office'))
            ->post(route('patient-intakes.link', $submission), ['patient_code' => $patient->patient_code]);

        $response->assertRedirect();
        $this->assertDatabaseHas('patient_intake_submissions', [
            'id' => $submission->id,
            'patient_id' => $patient->id,
            'verification_status' => 'verified',
        ]);
    }

    public function test_front_office_can_filter_the_intake_queue_and_update_verification_status(): void
    {
        $pendingSubmission = PatientIntakeSubmission::factory()->create([
            'response_id' => 'form-response-pending',
            'child_data' => ['child_name' => 'Menunggu'],
            'verification_status' => 'pending',
        ]);
        PatientIntakeSubmission::factory()->create([
            'response_id' => 'form-response-verified',
            'child_data' => ['child_name' => 'Selesai'],
            'verification_status' => 'verified',
        ]);
        $frontOffice = $this->userWithRole('front_office');

        $this->actingAs($frontOffice)->get(route('patient-intakes.index', ['status' => 'pending']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PatientIntakes/Index')
                ->where('filters.status', 'pending')
                ->where('submissions.total', 1)
                ->where('submissions.data.0.response_id', 'form-response-pending'));

        $this->actingAs($frontOffice)->patch(route('patient-intakes.status.update', $pendingSubmission), [
            'verification_status' => 'needs_review',
        ])->assertRedirect();

        $this->assertDatabaseHas('patient_intake_submissions', [
            'id' => $pendingSubmission->id,
            'verification_status' => 'needs_review',
        ]);
    }

    public function test_front_office_can_create_a_patient_from_an_intake_after_manual_review(): void
    {
        $submission = PatientIntakeSubmission::factory()->create([
            'response_id' => 'form-response-create-patient',
            'child_data' => ['child_name' => 'Nama Dari Form'],
        ]);
        $frontOffice = $this->userWithRole('front_office');

        $response = $this->actingAs($frontOffice)->post(route('patient-intakes.create-patient', $submission), [
            'name' => 'Nama Dari Form',
            'birth_date' => '2021-02-10',
            'address' => 'Alamat keluarga',
            'mother_name' => 'Ibu Dari Form',
            'mother_whatsapp' => '081234567890',
            'father_name' => 'Ayah Dari Form',
            'father_whatsapp' => '081234567891',
        ]);

        $response->assertRedirect(route('patient-intakes.show', $submission));
        $patient = Patient::query()->where('name', 'Nama Dari Form')->firstOrFail();
        $this->assertSame('081234567890', $patient->primary_whatsapp);
        $this->assertSame($frontOffice->id, $patient->created_by);
        $this->assertDatabaseHas('patient_intake_submissions', [
            'id' => $submission->id,
            'patient_id' => $patient->id,
            'verification_status' => 'verified',
        ]);
        $this->assertSame(2, ParentContact::query()->count());
        $this->assertSame(2, $patient->parents()->count());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postSignedWebhook(array $payload, ?string $secret = null, ?string $timestamp = null): TestResponse
    {
        $secret ??= self::WEBHOOK_SECRET;
        $timestamp ??= (string) now()->timestamp;
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $this->call('POST', '/api/integrations/google-form/intakes', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_KASUARI_TIMESTAMP' => $timestamp,
            'HTTP_X_KASUARI_SIGNATURE' => hash_hmac('sha256', $timestamp.'.'.$body, $secret),
        ], $body);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $roleRecord = Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user->assignRole($roleRecord);

        return $user;
    }
}
