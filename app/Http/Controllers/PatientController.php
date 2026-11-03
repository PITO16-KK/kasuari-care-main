<?php

namespace App\Http\Controllers;

use App\Models\ParentContact;
use App\Models\Patient;
use App\Models\TherapistProfile;
use App\Models\TherapyType;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PatientController extends Controller
{
    public function index(Request $request): Response
    {
        $therapyTypes = TherapyType::query()
            ->whereIn('code', ['TO', 'TW'])
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
        $therapyTypeCodes = $therapyTypes->pluck('code', 'id')->all();
        $patients = Patient::query()
            ->with(['parents:id,name,whatsapp', 'therapists:id,user_id,specialty'])
            ->when($request->string('q')->trim()->value(), fn ($query, $term) => $query
                ->where(fn ($query) => $query->where('name', 'like', "%{$term}%")
                    ->orWhere('patient_code', 'like', "%{$term}%")))
            ->when($request->input('status') === 'active', fn ($query) => $query->where('is_active', true))
            ->when($request->input('status') === 'archived', fn ($query) => $query->where('is_active', false))
            ->orderByDesc('is_active')->latest()->paginate(12)->withQueryString();
        $patients->getCollection()->each(function (Patient $patient) use ($therapyTypeCodes): void {
            $assignments = [];

            foreach ($patient->therapists as $therapist) {
                $code = $therapyTypeCodes[$therapist->pivot->therapy_type_id] ?? null;

                if ($code !== null) {
                    $assignments[$code] = (int) $therapist->id;
                }
            }

            $patient->setAttribute('therapist_assignments', $assignments);
            $patient->unsetRelation('therapists');
        });
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

        return Inertia::render('Patients/Index', [
            'patients' => $patients,
            'filters' => $request->only('q', 'status'),
            'therapyTypes' => $therapyTypes,
            'therapists' => $therapists,
        ]);
    }

    public function details(Request $request, Patient $patient): JsonResponse
    {
        $packages = DB::table('patient_packages')
            ->join('package_catalog', 'package_catalog.id', '=', 'patient_packages.package_catalog_id')
            ->where('patient_packages.patient_id', $patient->id)
            ->orderByDesc('patient_packages.starts_at')
            ->get([
                'patient_packages.id',
                'package_catalog.name',
                'patient_packages.status',
                'patient_packages.starts_at',
                'patient_packages.expires_at',
            ]);
        $packageIds = $packages->pluck('id');
        $durationEntitlements = DB::table('duration_package_entitlements')
            ->whereIn('patient_package_id', $packageIds)
            ->orderBy('duration_minutes')
            ->get(['patient_package_id', 'duration_minutes', 'granted_sessions', 'remaining_sessions'])
            ->groupBy('patient_package_id');
        $therapyEntitlements = DB::table('package_entitlements')
            ->join('therapy_types', 'therapy_types.id', '=', 'package_entitlements.therapy_type_id')
            ->whereIn('package_entitlements.patient_package_id', $packageIds)
            ->orderBy('therapy_types.name')
            ->get([
                'package_entitlements.patient_package_id',
                'therapy_types.name as therapy_name',
                'package_entitlements.granted_sessions',
                'package_entitlements.remaining_sessions',
            ])
            ->groupBy('patient_package_id');

        $packages = $packages->map(function (object $package) use ($durationEntitlements, $therapyEntitlements): array {
            $entitlements = $durationEntitlements->get($package->id, collect())
                ->map(fn (object $entitlement): array => [
                    'name' => "Sesi {$entitlement->duration_minutes} menit",
                    'granted_sessions' => (int) $entitlement->granted_sessions,
                    'remaining_sessions' => (int) $entitlement->remaining_sessions,
                ])
                ->concat($therapyEntitlements->get($package->id, collect())
                    ->map(fn (object $entitlement): array => [
                        'name' => $entitlement->therapy_name,
                        'granted_sessions' => (int) $entitlement->granted_sessions,
                        'remaining_sessions' => (int) $entitlement->remaining_sessions,
                    ]))
                ->values();

            return [
                'id' => (int) $package->id,
                'name' => $package->name,
                'status' => $package->status,
                'starts_at' => $package->starts_at,
                'expires_at' => $package->expires_at,
                'entitlements' => $entitlements,
            ];
        });

        $sessions = $patient->appointments()
            ->where('status', 'completed')
            ->where('attendance_status', 'present')
            ->whereHas('report', fn ($query) => $query->where('status', 'submitted'))
            ->with([
                'therapyType:id,name',
                'therapist.user:id,name',
                'report:id,appointment_id,activities,parent_notes,homework,submitted_at',
            ])
            ->orderByDesc('starts_at')
            ->paginate(15, [
                'appointments.id',
                'appointments.patient_id',
                'appointments.therapy_type_id',
                'appointments.therapist_profile_id',
                'appointments.starts_at',
            ], 'page', max(1, $request->integer('page', 1)))
            ->through(fn ($appointment): array => [
                'id' => $appointment->id,
                'starts_at' => $appointment->starts_at?->toIso8601String(),
                'therapy_name' => $appointment->therapyType?->name,
                'therapist_name' => $appointment->therapist?->user?->name,
                'report' => [
                    'activities' => $appointment->report?->activities,
                    'parent_notes' => $appointment->report?->parent_notes,
                    'homework' => $appointment->report?->homework,
                    'submitted_at' => $appointment->report?->submitted_at?->toIso8601String(),
                ],
            ]);

        return response()->json([
            'packages' => $packages,
            'sessions' => $sessions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->patientRules());
        $this->validateDistinctTherapistAssignments($data);

        DB::transaction(function () use ($data, $request): void {
            $patient = Patient::create([
                ...collect($data)->only(['name', 'birth_date', 'gender', 'address', 'primary_whatsapp', 'main_complaint', 'school_name', 'medical_history'])->all(),
                'patient_code' => Patient::nextCode(),
                'created_by' => $request->user()->id,
            ]);

            $this->syncParents($patient, $data);
            $patient->syncTherapistAssignments($data['therapist_assignments'] ?? []);
        });

        return redirect()->route('patients.index')->with('success', 'Data pasien berhasil disimpan.');
    }

    public function update(Request $request, Patient $patient): RedirectResponse
    {
        $data = $request->validate($this->patientRules($patient));
        $this->validateDistinctTherapistAssignments($data);

        DB::transaction(function () use ($data, $patient): void {
            $patient->update(collect($data)->only([
                'name', 'birth_date', 'gender', 'address', 'primary_whatsapp',
                'main_complaint', 'school_name', 'medical_history',
            ])->all());

            $this->syncParents($patient, $data);
            $patient->syncTherapistAssignments($data['therapist_assignments'] ?? []);
        });

        return redirect()->route('patients.index')->with('success', 'Data pasien berhasil diperbarui.');
    }

    public function destroy(Patient $patient): RedirectResponse
    {
        $patient->update(['is_active' => false]);

        return redirect()->route('patients.index')->with('success', 'Pasien dipindahkan ke arsip. Riwayat dan jadwal tetap tersimpan.');
    }

    public function restore(Patient $patient): RedirectResponse
    {
        $patient->update(['is_active' => true]);

        return redirect()->route('patients.index')->with('success', 'Pasien berhasil diaktifkan kembali.');
    }

    /** @return array<string, list<string>> */
    private function patientRules(?Patient $patient = null): array
    {
        $uniqueTherapist = Rule::unique('patient_therapists', 'therapist_profile_id');

        if ($patient !== null) {
            $uniqueTherapist->where(fn (Builder $query) => $query->where('patient_id', '!=', $patient->id));
        }

        return [
            'name' => ['required', 'string', 'max:160'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', 'in:male,female'],
            'address' => ['nullable', 'string', 'max:2000'],
            'primary_whatsapp' => ['nullable', 'string', 'max:30'],
            'main_complaint' => ['nullable', 'string', 'max:3000'],
            'school_name' => ['nullable', 'string', 'max:160'],
            'medical_history' => ['nullable', 'string', 'max:3000'],
            'father_name' => ['nullable', 'string', 'max:160'],
            'father_whatsapp' => ['nullable', 'string', 'max:30'],
            'mother_name' => ['nullable', 'string', 'max:160'],
            'mother_whatsapp' => ['nullable', 'string', 'max:30'],
            'therapist_assignments' => ['nullable', 'array:TO,TW'],
            'therapist_assignments.*' => ['nullable', 'integer', 'exists:therapist_profiles,id', $uniqueTherapist],
        ];
    }

    /** @param array<string, mixed> $data */
    private function validateDistinctTherapistAssignments(array $data): void
    {
        $assignments = collect($data['therapist_assignments'] ?? [])->filter();
        $errors = [];

        foreach ($assignments as $code => $therapistProfileId) {
            if ($assignments->filter(fn (mixed $id): bool => (int) $id === (int) $therapistProfileId)->count() > 1) {
                $errors["therapist_assignments.{$code}"] = 'Terapis okupasi dan wicara harus berbeda.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @param array<string, mixed> $data */
    private function syncParents(Patient $patient, array $data): void
    {
        $hasFather = filled($data['father_name'] ?? null);
        $hasMother = filled($data['mother_name'] ?? null);

        $this->syncParent($patient, 'father', $data['father_name'] ?? null, $data['father_whatsapp'] ?? null, $hasFather && ! $hasMother);
        $this->syncParent($patient, 'mother', $data['mother_name'] ?? null, $data['mother_whatsapp'] ?? null, $hasMother);

        if ($hasFather || $hasMother) {
            $patient->parents()
                ->wherePivotNotIn('relation', ['father', 'mother'])
                ->get()
                ->each(fn (ParentContact $parent) => $patient->parents()->updateExistingPivot($parent->id, ['is_primary_contact' => false]));
        }
    }

    private function syncParent(Patient $patient, string $relation, ?string $name, ?string $whatsapp, bool $isPrimary): void
    {
        $existingParent = $patient->parents()->wherePivot('relation', $relation)->first();

        if (blank($name)) {
            if ($existingParent) {
                $patient->parents()->detach($existingParent->id);

                if (! $existingParent->patients()->exists()) {
                    $existingParent->delete();
                }
            }

            return;
        }

        $isSharedWithAnotherPatient = $existingParent
            && $existingParent->patients()->where('patients.id', '!=', $patient->id)->exists();

        if ($isSharedWithAnotherPatient && ($existingParent->name !== $name || $existingParent->whatsapp !== $whatsapp)) {
            $parent = ParentContact::create(['name' => $name, 'whatsapp' => $whatsapp]);
            $patient->parents()->detach($existingParent->id);
            $patient->parents()->attach($parent->id, [
                'relation' => $relation,
                'is_primary_contact' => $isPrimary,
            ]);

            return;
        }

        if ($existingParent) {
            $existingParent->update(['name' => $name, 'whatsapp' => $whatsapp]);
            $patient->parents()->updateExistingPivot($existingParent->id, ['is_primary_contact' => $isPrimary]);

            return;
        }

        $parent = ParentContact::create(['name' => $name, 'whatsapp' => $whatsapp]);
        $patient->parents()->attach($parent->id, [
            'relation' => $relation,
            'is_primary_contact' => $isPrimary,
        ]);
    }
}
