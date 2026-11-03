<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Assessment;
use App\Models\Patient;
use App\Models\Sale;
use App\Models\TherapistProfile;
use App\Models\TherapyType;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentController extends Controller
{
    public function index(): Response
    {
        $assessments = Assessment::with([
            'patient:id,patient_code,name',
            'patient.therapists:id,user_id,specialty',
            'patient.therapists.user:id,name',
            'therapist.user:id,name',
            'sale:id,invoice_number,status,subtotal,discount_total,grand_total,paid_at',
            'sale.payment',
            'sale.lines',
        ])
            ->withCount('appointments')
            ->latest()
            ->paginate(20)
            ->withQueryString();
        $serviceIds = $assessments->getCollection()
            ->flatMap(fn (Assessment $assessment) => $assessment->sale?->lines->pluck('service_catalog_id') ?? [])
            ->filter()
            ->unique();
        $serviceNames = DB::table('service_catalog')
            ->whereIn('id', $serviceIds)
            ->pluck('name', 'id');
        $therapyTypes = TherapyType::query()
            ->whereIn('code', ['TO', 'TW'])
            ->get(['id', 'name'])
            ->keyBy('id');

        $assessments->getCollection()->each(function (Assessment $assessment) use ($serviceNames, $therapyTypes): void {
            $assessment->service_name = $serviceNames->get($assessment->sale?->lines->first()?->service_catalog_id);
            $assessment->patient->setAttribute(
                'recommended_therapists',
                $this->recommendedTherapists($assessment->patient, $therapyTypes),
            );
            $assessment->patient->unsetRelation('therapists');
        });

        return Inertia::render('Assessments/Index', [
            'assessments' => $assessments,
        ]);
    }

    public function create(Request $request): Response
    {
        $validated = $request->validate([
            'patient_id' => ['nullable', 'integer', Rule::exists('patients', 'id')->where('is_active', true)],
        ]);

        return Inertia::render('Assessments/Create', [
            'patients' => Patient::where('is_active', true)->orderBy('name')->get(['id', 'patient_code', 'name']),
            'initialPatientId' => isset($validated['patient_id']) ? (int) $validated['patient_id'] : null,
            'services' => DB::table('service_catalog')
                ->where('is_active', true)
                ->where('service_type', 'assessment')
                ->orderBy('name')
                ->get(['id', 'name', 'price', 'description']),
            'therapists' => TherapistProfile::with('user:id,name')
                ->orderBy('id')
                ->get(['id', 'user_id', 'specialty']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', Rule::exists('patients', 'id')->where('is_active', true)],
            'scheduled_at' => ['required', 'date', 'after_or_equal:now'],
            'therapist_profile_id' => ['required', 'exists:therapist_profiles,id'],
            'service_catalog_id' => [
                'required',
                Rule::exists('service_catalog', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->where('service_type', 'assessment')),
            ],
        ]);
        $startsAt = Carbon::parse($data['scheduled_at']);
        $endsAt = $startsAt->copy()->addMinutes(Assessment::SCHEDULE_DURATION_MINUTES);

        $assessment = DB::transaction(function () use ($data, $request, $startsAt, $endsAt): Assessment {
            Patient::query()->whereKey($data['patient_id'])->lockForUpdate()->firstOrFail();
            TherapistProfile::query()->whereKey($data['therapist_profile_id'])->lockForUpdate()->firstOrFail();
            $this->ensureTherapistAvailability((int) $data['therapist_profile_id'], $startsAt, $endsAt);

            $service = DB::table('service_catalog')
                ->where('id', $data['service_catalog_id'])
                ->where('is_active', true)
                ->where('service_type', 'assessment')
                ->lockForUpdate()
                ->first();

            abort_if($service === null, 404);

            $price = (int) $service->price;
            if ($price < 1) {
                throw ValidationException::withMessages(['service_catalog_id' => 'Harga layanan assessment harus lebih dari nol.']);
            }

            $assessment = Assessment::create([
                'patient_id' => $data['patient_id'],
                'therapist_profile_id' => $data['therapist_profile_id'],
                'scheduled_at' => $startsAt,
                'created_by' => $request->user()->id,
                'status' => 'awaiting_payment',
            ]);
            $sale = Sale::create([
                'invoice_number' => 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'patient_id' => $data['patient_id'],
                'cashier_id' => null,
                'status' => 'pending',
                'subtotal' => $price,
                'discount_total' => 0,
                'grand_total' => $price,
                'paid_at' => null,
            ]);
            $sale->lines()->create([
                'line_type' => 'assessment',
                'service_catalog_id' => $service->id,
                'description' => $service->name,
                'quantity' => 1,
                'unit_price' => $price,
                'line_total' => $price,
            ]);

            $assessment->update(['sale_id' => $sale->id]);

            return $assessment;
        });

        return redirect()->route('assessments.index')
            ->with('success', "Jadwal assessment dibuat dengan invoice {$assessment->sale->invoice_number}. Hasil dapat diisi setelah pembayaran.");
    }

    public function reschedule(Request $request, Assessment $assessment): RedirectResponse
    {
        $data = $request->validate([
            'scheduled_at' => ['required', 'date', 'after_or_equal:now'],
            'therapist_profile_id' => ['required', 'exists:therapist_profiles,id'],
        ]);
        $startsAt = Carbon::parse($data['scheduled_at']);
        $endsAt = $startsAt->copy()->addMinutes(Assessment::SCHEDULE_DURATION_MINUTES);

        DB::transaction(function () use ($assessment, $data, $startsAt, $endsAt): void {
            $lockedAssessment = Assessment::query()->whereKey($assessment->id)->lockForUpdate()->firstOrFail();

            if (! in_array($lockedAssessment->status, ['awaiting_payment', 'paid', 'scheduled'], true)) {
                throw ValidationException::withMessages([
                    'scheduled_at' => 'Assessment yang sudah selesai tidak dapat dijadwalkan ulang.',
                ]);
            }

            TherapistProfile::query()->whereKey($data['therapist_profile_id'])->lockForUpdate()->firstOrFail();
            $this->ensureTherapistAvailability(
                (int) $data['therapist_profile_id'],
                $startsAt,
                $endsAt,
                $lockedAssessment->id,
            );

            $lockedAssessment->update([
                'scheduled_at' => $startsAt,
                'therapist_profile_id' => $data['therapist_profile_id'],
            ]);
        });

        return redirect()->route('scheduling.index')->with('success', 'Jadwal assessment berhasil diperbarui.');
    }

    public function complete(Assessment $assessment): Response
    {
        $assessment->load([
            'patient:id,patient_code,name',
            'sale:id,invoice_number,status,grand_total,paid_at',
            'recommendations',
        ]);

        abort_unless($assessment->status === 'paid' && $assessment->sale?->status === 'paid', 403);

        $therapyTypes = TherapyType::query()
            ->whereIn('code', ['TO', 'TW'])
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
        $therapistAssignments = DB::table('patient_therapists')
            ->join('therapy_types', 'therapy_types.id', '=', 'patient_therapists.therapy_type_id')
            ->where('patient_therapists.patient_id', $assessment->patient_id)
            ->whereIn('therapy_types.code', ['TO', 'TW'])
            ->pluck('patient_therapists.therapist_profile_id', 'therapy_types.code')
            ->map(fn (int $id): int => $id)
            ->all();
        $assignedPatientIds = DB::table('patient_therapists')->pluck('patient_id', 'therapist_profile_id');
        $therapists = TherapistProfile::query()
            ->with('user:id,name')
            ->orderBy('id')
            ->get(['id', 'user_id', 'specialty'])
            ->map(fn (TherapistProfile $therapist): array => [
                'id' => (int) $therapist->id,
                'name' => $therapist->user?->name,
                'specialty' => $therapist->specialty,
                'assigned_patient_id' => $assignedPatientIds->get($therapist->id) === null
                    ? null
                    : (int) $assignedPatientIds->get($therapist->id),
            ]);

        return Inertia::render('Assessments/Complete', [
            'assessment' => $assessment,
            'therapyTypes' => $therapyTypes,
            'therapistAssignments' => $therapistAssignments,
            'therapists' => $therapists,
        ]);
    }

    public function update(Request $request, Assessment $assessment): RedirectResponse
    {
        $uniqueTherapist = Rule::unique('patient_therapists', 'therapist_profile_id')
            ->where(fn (Builder $query) => $query->where('patient_id', '!=', $assessment->patient_id));
        $data = $request->validate([
            'chief_complaint' => ['required', 'string', 'max:3000'],
            'development_history' => ['nullable', 'array'],
            'development_history.*' => ['nullable', 'string', 'max:2000'],
            'learning_behavior' => ['nullable', 'array'],
            'learning_behavior.*' => ['nullable', 'string', 'max:2000'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'referral_notes' => ['nullable', 'string', 'max:3000'],
            'recommendations' => ['nullable', 'array'],
            'recommendations.*.therapy_type_id' => ['required', 'exists:therapy_types,id'],
            'recommendations.*.sessions_per_week' => ['required', 'integer', 'min:1', 'max:7'],
            'therapist_assignments' => ['nullable', 'array:TO,TW'],
            'therapist_assignments.*' => ['nullable', 'integer', 'exists:therapist_profiles,id', $uniqueTherapist],
        ]);
        $therapistAssignments = $data['therapist_assignments'] ?? [];
        $this->validateDistinctTherapistAssignments($therapistAssignments);

        DB::transaction(function () use ($assessment, $data, $therapistAssignments): void {
            $lockedAssessment = Assessment::query()
                ->with('sale')
                ->whereKey($assessment->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($lockedAssessment->status === 'paid' && $lockedAssessment->sale?->status === 'paid', 403);
            $patient = Patient::query()->whereKey($lockedAssessment->patient_id)->lockForUpdate()->firstOrFail();
            $therapistIds = collect($therapistAssignments)->filter()->map(fn (mixed $id): int => (int) $id)->unique()->sort()->values();

            if ($therapistIds->isNotEmpty()) {
                TherapistProfile::query()->whereKey($therapistIds)->orderBy('id')->lockForUpdate()->get();

                if (DB::table('patient_therapists')
                    ->whereIn('therapist_profile_id', $therapistIds)
                    ->where('patient_id', '!=', $patient->id)
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'therapist_assignments' => 'Terapis yang dipilih sudah ditugaskan kepada anak lain.',
                    ]);
                }
            }

            $lockedAssessment->update([
                ...collect($data)->only([
                    'chief_complaint', 'development_history', 'learning_behavior', 'summary', 'referral_notes',
                ])->all(),
                'status' => 'completed',
                'completed_at' => now(),
            ]);
            $patient->syncTherapistAssignments($therapistAssignments);

            if (array_key_exists('recommendations', $data)) {
                $lockedAssessment->recommendations()->delete();

                foreach ($data['recommendations'] ?? [] as $recommendation) {
                    $lockedAssessment->recommendations()->create($recommendation);
                }
            }
        });

        return redirect()->route('assessments.index')
            ->with('success', 'Hasil assessment berhasil dikirim.')
            ->with('assessmentPdfUrl', route('assessments.pdf', $assessment));
    }

    /** @param array<string, int|string|null> $assignments */
    private function validateDistinctTherapistAssignments(array $assignments): void
    {
        $selectedIds = collect($assignments)->filter()->map(fn (mixed $id): int => (int) $id);

        if ($selectedIds->count() !== $selectedIds->unique()->count()) {
            throw ValidationException::withMessages([
                'therapist_assignments' => 'Terapis okupasi dan wicara harus berbeda.',
            ]);
        }
    }

    public function pdf(Assessment $assessment): \Illuminate\Http\Response
    {
        abort_unless(in_array($assessment->status, ['scheduled', 'completed'], true), 403);

        $assessment->load(['patient.parents', 'patient.therapists.user']);
        $therapyTypes = TherapyType::query()
            ->whereIn('code', ['TO', 'TW'])
            ->get(['id', 'name'])
            ->keyBy('id');
        $assessment->patient->setAttribute(
            'recommended_therapists',
            $this->recommendedTherapists($assessment->patient, $therapyTypes),
        );
        $assessment->patient->unsetRelation('therapists');

        return Pdf::loadView('pdf.assessment', compact('assessment'))
            ->setPaper('a4')
            ->stream("assessment-{$assessment->patient->patient_code}.pdf");
    }

    /** @return list<array{therapy_name: string|null, therapist_name: string|null}> */
    private function recommendedTherapists(Patient $patient, Collection $therapyTypes): array
    {
        return $patient->therapists
            ->map(function (TherapistProfile $therapist) use ($therapyTypes): array {
                return [
                    'therapy_name' => $therapyTypes->get((int) $therapist->pivot->therapy_type_id)?->name,
                    'therapist_name' => $therapist->user?->name,
                ];
            })
            ->filter(fn (array $assignment): bool => $assignment['therapy_name'] !== null)
            ->values()
            ->all();
    }

    private function ensureTherapistAvailability(
        int $therapistProfileId,
        Carbon $startsAt,
        Carbon $endsAt,
        ?int $ignoreAssessmentId = null,
    ): void {
        $appointmentConflict = Appointment::query()
            ->where('therapist_profile_id', $therapistProfileId)
            ->where('status', '!=', 'cancelled')
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();
        $assessmentConflict = Assessment::query()
            ->where('therapist_profile_id', $therapistProfileId)
            ->whereIn('status', ['awaiting_payment', 'paid', 'scheduled'])
            ->where('scheduled_at', '<', $endsAt)
            ->where('scheduled_at', '>', $startsAt->copy()->subMinutes(Assessment::SCHEDULE_DURATION_MINUTES))
            ->when($ignoreAssessmentId !== null, fn ($query) => $query->whereKeyNot($ignoreAssessmentId))
            ->exists();

        if ($appointmentConflict || $assessmentConflict) {
            throw ValidationException::withMessages([
                'therapist_profile_id' => 'Jadwal bentrok: terapis ini sudah menangani anak lain pada waktu tersebut. Silakan pilih waktu atau terapis lain.',
            ]);
        }
    }
}
