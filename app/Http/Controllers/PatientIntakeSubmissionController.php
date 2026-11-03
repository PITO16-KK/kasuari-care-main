<?php

namespace App\Http\Controllers;

use App\Models\ParentContact;
use App\Models\Patient;
use App\Models\PatientIntakeSubmission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PatientIntakeSubmissionController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status')->value();
        $allowedStatuses = ['pending', 'verified', 'needs_review'];

        if (! in_array($status, $allowedStatuses, true)) {
            $status = 'all';
        }

        $submissions = PatientIntakeSubmission::query()
            ->with('patient:id,patient_code,name')
            ->when($status !== 'all', fn (Builder $query) => $query->where('verification_status', $status))
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->paginate(20, [
                'id', 'response_id', 'submitted_at', 'child_data', 'verification_status', 'patient_id',
            ])
            ->withQueryString()
            ->through(fn (PatientIntakeSubmission $submission): array => [
                'id' => $submission->id,
                'response_id' => $submission->response_id,
                'submitted_at' => $submission->submitted_at?->toIso8601String(),
                'child_name' => data_get($submission->child_data, 'child_name'),
                'verification_status' => $submission->verification_status,
                'patient' => $submission->patient ? [
                    'id' => $submission->patient->id,
                    'patient_code' => $submission->patient->patient_code,
                    'name' => $submission->patient->name,
                ] : null,
            ]);

        return Inertia::render('PatientIntakes/Index', [
            'submissions' => $submissions,
            'filters' => ['status' => $status],
            'counts' => [
                'pending' => PatientIntakeSubmission::query()->where('verification_status', 'pending')->count(),
                'verified' => PatientIntakeSubmission::query()->where('verification_status', 'verified')->count(),
                'needs_review' => PatientIntakeSubmission::query()->where('verification_status', 'needs_review')->count(),
            ],
        ]);
    }

    public function show(Request $request, PatientIntakeSubmission $submission): Response
    {
        $submission->load('patient:id,patient_code,name');

        return Inertia::render('PatientIntakes/Show', [
            'submission' => $submission,
            'canManage' => $request->user()->hasAnyRole(['owner', 'front_office']),
        ]);
    }

    public function updateVerificationStatus(Request $request, PatientIntakeSubmission $submission): RedirectResponse
    {
        $data = $request->validate([
            'verification_status' => ['required', Rule::in(['pending', 'verified', 'needs_review'])],
        ]);

        $submission->update($data);

        return back()->with('success', 'Status verifikasi form berhasil diperbarui.');
    }

    public function linkPatient(Request $request, PatientIntakeSubmission $submission): RedirectResponse
    {
        $data = $request->validate([
            'patient_code' => ['required', 'string', 'exists:patients,patient_code'],
        ]);
        $patient = Patient::query()->where('patient_code', $data['patient_code'])->firstOrFail();

        DB::transaction(function () use ($submission, $patient): void {
            $lockedSubmission = PatientIntakeSubmission::query()
                ->whereKey($submission->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSubmission->patient_id !== null && $lockedSubmission->patient_id !== $patient->id) {
                throw ValidationException::withMessages([
                    'patient_code' => 'Form ini sudah ditautkan ke pasien lain.',
                ]);
            }

            $lockedSubmission->update([
                'patient_id' => $patient->id,
                'verification_status' => 'verified',
            ]);
        });

        return back()->with('success', "Form berhasil ditautkan ke pasien {$patient->patient_code}.");
    }

    public function createPatient(Request $request, PatientIntakeSubmission $submission): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'address' => ['nullable', 'string', 'max:2000'],
            'father_name' => ['nullable', 'string', 'max:160'],
            'father_whatsapp' => ['nullable', 'string', 'max:30'],
            'mother_name' => ['nullable', 'string', 'max:160'],
            'mother_whatsapp' => ['nullable', 'string', 'max:30'],
        ]);

        $patient = DB::transaction(function () use ($request, $submission, $data): Patient {
            $lockedSubmission = PatientIntakeSubmission::query()
                ->whereKey($submission->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSubmission->patient_id !== null) {
                throw ValidationException::withMessages([
                    'patient_id' => 'Form ini sudah ditautkan ke pasien.',
                ]);
            }

            $primaryWhatsapp = $data['mother_whatsapp'] ?? $data['father_whatsapp'] ?? null;
            $patient = Patient::query()->create([
                'patient_code' => Patient::nextCode(),
                'name' => $data['name'],
                'birth_date' => $data['birth_date'] ?? null,
                'address' => $data['address'] ?? null,
                'primary_whatsapp' => $primaryWhatsapp,
                'created_by' => $request->user()->id,
            ]);

            $primaryContactAssigned = false;

            foreach (['mother', 'father'] as $relation) {
                $parentName = $data["{$relation}_name"] ?? null;

                if (blank($parentName)) {
                    continue;
                }

                $parent = ParentContact::query()->create([
                    'name' => $parentName,
                    'whatsapp' => $data["{$relation}_whatsapp"] ?? null,
                ]);
                $patient->parents()->attach($parent->id, [
                    'relation' => $relation,
                    'is_primary_contact' => ! $primaryContactAssigned,
                ]);
                $primaryContactAssigned = true;
            }

            $lockedSubmission->update([
                'patient_id' => $patient->id,
                'verification_status' => 'verified',
            ]);

            return $patient;
        });

        return redirect()->route('patient-intakes.show', $submission)
            ->with('success', "Profil pasien {$patient->patient_code} berhasil dibuat dan ditautkan.");
    }
}
