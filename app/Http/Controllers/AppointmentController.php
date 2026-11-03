<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Assessment;
use App\Models\Patient;
use App\Models\Room;
use App\Models\Sale;
use App\Models\TherapistProfile;
use App\Models\TherapyType;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    private const EVALUATION_SESSION_INTERVAL = 25;

    public function index(Request $request): Response
    {
        $requestedPatientId = filter_var($request->query('patient_id'), FILTER_VALIDATE_INT) ?: null;
        $requestedAssessmentId = filter_var($request->query('assessment_id'), FILTER_VALIDATE_INT) ?: null;
        $initialPatientId = $requestedPatientId !== null
            && Patient::query()->whereKey($requestedPatientId)->where('is_active', true)->exists()
                ? $requestedPatientId
                : null;
        $initialAssessmentId = $initialPatientId !== null
            && $requestedAssessmentId !== null
            && Assessment::query()->whereKey($requestedAssessmentId)
                ->where('patient_id', $initialPatientId)
                ->where('status', 'completed')
                ->exists()
                ? $requestedAssessmentId
                : null;

        $latestPackageIds = DB::table('patient_packages as package_candidates')
            ->selectRaw('MAX(package_candidates.id)')
            ->groupBy('package_candidates.patient_id');
        $patientPackages = DB::table('patient_packages')
            ->join('package_catalog', 'package_catalog.id', '=', 'patient_packages.package_catalog_id')
            ->where(function ($query) use ($latestPackageIds): void {
                $query->where('patient_packages.status', 'active')
                    ->orWhereIn('patient_packages.id', $latestPackageIds);
            })
            ->where('patient_packages.status', '!=', 'completed')
            ->select([
                'patient_packages.id',
                'patient_packages.patient_id',
                'patient_packages.status',
                'patient_packages.starts_at',
                'patient_packages.expires_at',
                'package_catalog.name',
                'package_catalog.session_duration_minutes',
            ])
            ->selectSub(
                DB::table('duration_package_entitlements')
                    ->selectRaw('COALESCE(SUM(granted_sessions), 0)')
                    ->whereColumn('patient_package_id', 'patient_packages.id'),
                'duration_granted_sessions',
            )
            ->selectSub(
                DB::table('duration_package_entitlements')
                    ->selectRaw('COALESCE(SUM(remaining_sessions), 0)')
                    ->whereColumn('patient_package_id', 'patient_packages.id'),
                'duration_remaining_sessions',
            )
            ->selectSub(
                DB::table('package_entitlements')
                    ->selectRaw('COALESCE(SUM(granted_sessions), 0)')
                    ->whereColumn('patient_package_id', 'patient_packages.id'),
                'legacy_granted_sessions',
            )
            ->selectSub(
                DB::table('package_entitlements')
                    ->selectRaw('COALESCE(SUM(remaining_sessions), 0)')
                    ->whereColumn('patient_package_id', 'patient_packages.id'),
                'legacy_remaining_sessions',
            )
            ->orderByDesc('patient_packages.starts_at')
            ->get();
        $legacyEntitlements = DB::table('package_entitlements')
            ->join('therapy_types', 'therapy_types.id', '=', 'package_entitlements.therapy_type_id')
            ->whereIn('package_entitlements.patient_package_id', $patientPackages->pluck('id'))
            ->orderBy('therapy_types.name')
            ->get([
                'package_entitlements.patient_package_id',
                'package_entitlements.therapy_type_id',
                'therapy_types.name as therapy_name',
                'package_entitlements.granted_sessions',
                'package_entitlements.remaining_sessions',
            ])
            ->groupBy('patient_package_id');
        $durationEntitlements = DB::table('duration_package_entitlements')
            ->whereIn('patient_package_id', $patientPackages->pluck('id'))
            ->orderBy('duration_minutes')
            ->get(['patient_package_id', 'duration_minutes', 'granted_sessions', 'remaining_sessions'])
            ->groupBy('patient_package_id');
        $packageAppointments = $patientPackages->isEmpty()
            ? collect()
            : Appointment::query()
                ->whereIn('patient_id', $patientPackages->pluck('patient_id')->unique())
                ->whereIn('status', ['scheduled', 'confirmed', 'in_progress'])
                ->whereBetween('starts_at', [$patientPackages->min('starts_at'), $patientPackages->max('expires_at')])
                ->get(['id', 'patient_id', 'therapy_type_id', 'starts_at', 'ends_at']);
        $patientPackages = $patientPackages->map(function (object $package) use ($legacyEntitlements, $durationEntitlements, $packageAppointments): object {
            $packageStartsAt = Carbon::parse($package->starts_at);
            $packageExpiresAt = Carbon::parse($package->expires_at);
            $bookedAppointments = $packageAppointments->filter(function (Appointment $appointment) use ($package, $packageStartsAt, $packageExpiresAt): bool {
                return (int) $appointment->patient_id === (int) $package->patient_id
                    && $appointment->starts_at->greaterThanOrEqualTo($packageStartsAt)
                    && $appointment->starts_at->lessThanOrEqualTo($packageExpiresAt);
            });
            $package->duration_entitlements = $durationEntitlements->get($package->id, collect())->map(function (object $entitlement) use ($bookedAppointments, $package): object {
                $matchingAppointments = $bookedAppointments->filter(function (Appointment $appointment) use ($entitlement): bool {
                    return (int) round($appointment->starts_at->diffInMinutes($appointment->ends_at)) === (int) $entitlement->duration_minutes;
                })->sort(function (Appointment $first, Appointment $second): int {
                    return $first->starts_at->getTimestamp() <=> $second->starts_at->getTimestamp()
                        ?: $first->id <=> $second->id;
                })->values();
                $entitlement->booked_sessions = $matchingAppointments->count();
                $entitlement->covered_appointment_ids = $matchingAppointments
                    ->take($package->status === 'active' ? (int) $entitlement->remaining_sessions : 0)
                    ->pluck('id')
                    ->map(fn (int $appointmentId): int => $appointmentId)
                    ->all();
                $entitlement->available_to_schedule = max(0, min(
                    (int) $entitlement->remaining_sessions - $entitlement->booked_sessions,
                    (int) $entitlement->granted_sessions,
                ));

                return $entitlement;
            })->values();
            $package->duration_booked_sessions = $package->duration_entitlements->sum('booked_sessions');
            $package->duration_available_sessions = $package->duration_entitlements->sum('available_to_schedule');
            $package->legacy_entitlements = $legacyEntitlements->get($package->id, collect())->map(function (object $entitlement) use ($bookedAppointments, $package): object {
                $matchingAppointments = $bookedAppointments
                    ->where('therapy_type_id', (int) $entitlement->therapy_type_id)
                    ->sort(function (Appointment $first, Appointment $second): int {
                        return $first->starts_at->getTimestamp() <=> $second->starts_at->getTimestamp()
                            ?: $first->id <=> $second->id;
                    })->values();
                $entitlement->booked_sessions = $matchingAppointments->count();
                $entitlement->covered_appointment_ids = $matchingAppointments
                    ->take($package->status === 'active' ? (int) $entitlement->remaining_sessions : 0)
                    ->pluck('id')
                    ->map(fn (int $appointmentId): int => $appointmentId)
                    ->all();
                $entitlement->available_to_schedule = max(0, min(
                    (int) $entitlement->remaining_sessions - $entitlement->booked_sessions,
                    (int) $entitlement->granted_sessions,
                ));

                return $entitlement;
            })->values();
            $package->legacy_booked_sessions = $package->legacy_entitlements->sum('booked_sessions');
            $package->legacy_available_sessions = $package->legacy_entitlements->sum('available_to_schedule');

            return $package;
        });
        $coveredAppointmentIds = $patientPackages->flatMap(function (object $package): array {
            return [
                ...$package->duration_entitlements->flatMap(fn (object $entitlement) => $entitlement->covered_appointment_ids)->all(),
                ...$package->legacy_entitlements->flatMap(fn (object $entitlement) => $entitlement->covered_appointment_ids)->all(),
            ];
        })->map(fn (int $appointmentId): int => $appointmentId)->unique()->values();
        $latestAssessmentCompletion = DB::table('assessments')
            ->select('patient_id')
            ->selectRaw('MAX(completed_at) as latest_evaluation_at')
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->groupBy('patient_id');
        $patientEvaluationSessionCounts = DB::table('appointments')
            ->join('session_reports', 'session_reports.appointment_id', '=', 'appointments.id')
            ->join('patients', 'patients.id', '=', 'appointments.patient_id')
            ->leftJoinSub($latestAssessmentCompletion, 'evaluation_activity', function ($join): void {
                $join->on('evaluation_activity.patient_id', '=', 'appointments.patient_id');
            })
            ->where('patients.is_active', true)
            ->where('appointments.status', 'completed')
            ->where('appointments.attendance_status', 'present')
            ->where('session_reports.status', 'submitted')
            ->where(function ($query): void {
                $query->whereNull('evaluation_activity.latest_evaluation_at')
                    ->orWhereColumn('appointments.starts_at', '>', 'evaluation_activity.latest_evaluation_at');
            })
            ->select('appointments.patient_id')
            ->selectRaw('COUNT(appointments.id) as sessions_since_evaluation')
            ->groupBy('appointments.patient_id')
            ->get()
            ->map(fn (object $patient): array => [
                'patient_id' => (int) $patient->patient_id,
                'sessions_since_evaluation' => (int) $patient->sessions_since_evaluation,
            ])
            ->values();

        $appointments = Appointment::with(['patient:id,patient_code,name,primary_whatsapp', 'therapyType:id,code,name', 'therapist.user:id,name', 'room:id,name', 'assessment:id,patient_id', 'sale:id,appointment_id,status'])
            ->where('starts_at', '>=', now()->startOfDay())->where('status', '!=', 'cancelled')
            ->orderBy('starts_at')->limit(60)->get();
        $appointments->each(function (Appointment $appointment) use ($coveredAppointmentIds): void {
            $appointment->payment_warning = $appointment->status !== 'completed'
                && $appointment->sale?->status !== 'paid'
                && ! $coveredAppointmentIds->contains((int) $appointment->id);
        });
        $patientTherapists = DB::table('patient_therapists')
            ->join('therapy_types', 'therapy_types.id', '=', 'patient_therapists.therapy_type_id')
            ->join('therapist_profiles', 'therapist_profiles.id', '=', 'patient_therapists.therapist_profile_id')
            ->join('users', 'users.id', '=', 'therapist_profiles.user_id')
            ->orderBy('patient_therapists.patient_id')
            ->orderBy('therapy_types.code')
            ->get([
                'patient_therapists.patient_id',
                'therapy_types.id as therapy_type_id',
                'therapy_types.code as therapy_code',
                'therapist_profiles.id as therapist_profile_id',
                'therapist_profiles.specialty',
                'users.name as therapist_name',
            ]);

        return Inertia::render('Scheduling/Index', [
            'appointments' => $appointments,
            'scheduledAssessments' => Assessment::with(['patient:id,patient_code,name', 'therapist.user:id,name'])
                ->whereIn('status', ['awaiting_payment', 'paid', 'scheduled'])
                ->where('scheduled_at', '>=', now()->startOfDay())
                ->orderBy('scheduled_at')->limit(60)
                ->get(['id', 'patient_id', 'therapist_profile_id', 'scheduled_at', 'status']),
            'patients' => Patient::where('is_active', true)->orderBy('name')->get(['id', 'patient_code', 'name']),
            'initialPatientId' => $initialPatientId,
            'initialAssessmentId' => $initialAssessmentId,
            'assessments' => Assessment::where('status', 'completed')
                ->with('recommendations.therapyType:id,code,name')
                ->latest()
                ->get(['id', 'patient_id', 'scheduled_at', 'chief_complaint', 'summary', 'created_at']),
            'patientPackages' => $patientPackages,
            'patientEvaluationSessionCounts' => $patientEvaluationSessionCounts,
            'patientTherapists' => $patientTherapists,
            'therapyTypes' => TherapyType::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'default_duration_minutes']),
            'therapists' => TherapistProfile::with('user:id,name')->get(['id', 'user_id', 'specialty']),
            'rooms' => Room::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', 'exists:patients,id'],
            'assessment_id' => ['required', Rule::exists('assessments', 'id')->where(fn ($query) => $query
                ->where('patient_id', $request->input('patient_id'))
                ->where('status', 'completed'))],
            'therapy_type_id' => ['required', 'exists:therapy_types,id'],
            'therapist_profile_id' => ['required', 'integer', $this->therapistAssignmentRule($request)],
            'room_id' => ['required', 'exists:rooms,id'],
            'starts_at' => ['required', 'date', 'after_or_equal:now'],
        ], [
            'therapist_profile_id.exists' => 'Profil terapis tidak valid atau belum direkomendasikan untuk pasien dan jenis terapi ini.',
        ]);

        $startsAt = Carbon::parse($data['starts_at']);

        try {
            DB::transaction(function () use ($data, $startsAt, $request): void {
                Patient::query()->whereKey($data['patient_id'])->lockForUpdate()->firstOrFail();
                $this->ensurePatientEvaluationIsComplete((int) $data['patient_id']);

                $durationMinutes = $this->durationForAppointment($data, $startsAt);
                $endsAt = $startsAt->copy()->addMinutes($durationMinutes);

                $this->lockSchedulingResources($data);

                if ($this->hasAppointmentConflict('therapist_profile_id', (int) $data['therapist_profile_id'], $startsAt, $endsAt)) {
                    throw ValidationException::withMessages(['starts_at' => 'Jadwal bentrok: terapis ini sudah menangani anak lain pada waktu tersebut. Silakan pilih waktu atau terapis lain.']);
                }

                if ($this->hasAppointmentConflict('room_id', (int) $data['room_id'], $startsAt, $endsAt)) {
                    throw ValidationException::withMessages(['starts_at' => 'Jadwal bentrok: ruangan ini sedang digunakan pada waktu tersebut. Silakan pilih waktu atau ruangan lain.']);
                }

                Appointment::create([
                    ...$data,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'created_by' => $request->user()->id,
                ]);

                $this->syncPendingTherapyInvoices((int) $data['patient_id']);
            });
        } catch (QueryException $exception) {
            $this->handleSchedulingConstraintViolation($exception);
        }

        return redirect()->route('scheduling.index')->with('success', 'Jadwal terapi berhasil dibuat. Sesi tanpa kuota paket langsung masuk ke Tagihan.');
    }

    public function update(Request $request, Appointment $appointment): RedirectResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', 'exists:patients,id'],
            'assessment_id' => ['required', Rule::exists('assessments', 'id')->where(fn ($query) => $query
                ->where('patient_id', $request->input('patient_id'))
                ->where('status', 'completed'))],
            'therapy_type_id' => ['required', 'exists:therapy_types,id'],
            'therapist_profile_id' => ['required', 'integer', $this->therapistAssignmentRule($request)],
            'room_id' => ['required', 'exists:rooms,id'],
            'starts_at' => ['required', 'date', 'after_or_equal:now'],
        ], [
            'therapist_profile_id.exists' => 'Profil terapis tidak valid atau belum direkomendasikan untuk pasien dan jenis terapi ini.',
        ]);

        $startsAt = Carbon::parse($data['starts_at']);

        try {
            DB::transaction(function () use ($appointment, $data, $startsAt): void {
                Sale::query()->where('appointment_id', $appointment->id)->lockForUpdate()->first();
                $lockedAppointment = Appointment::query()->whereKey($appointment->id)->lockForUpdate()->firstOrFail();
                $previousPatientId = (int) $lockedAppointment->patient_id;

                if (! in_array($lockedAppointment->status, ['scheduled', 'confirmed'], true)) {
                    throw ValidationException::withMessages(['starts_at' => 'Hanya jadwal yang belum dimulai yang dapat diubah.']);
                }

                $this->ensurePatientEvaluationIsComplete((int) $data['patient_id']);

                $durationMinutes = $this->durationForAppointment($data, $startsAt, $lockedAppointment->id);
                $endsAt = $startsAt->copy()->addMinutes($durationMinutes);

                $this->lockSchedulingResources($data);

                if ($this->hasAppointmentConflict('therapist_profile_id', (int) $data['therapist_profile_id'], $startsAt, $endsAt, $lockedAppointment->id)) {
                    throw ValidationException::withMessages(['starts_at' => 'Jadwal bentrok: terapis ini sudah menangani anak lain pada waktu tersebut. Silakan pilih waktu atau terapis lain.']);
                }

                if ($this->hasAppointmentConflict('room_id', (int) $data['room_id'], $startsAt, $endsAt, $lockedAppointment->id)) {
                    throw ValidationException::withMessages(['starts_at' => 'Jadwal bentrok: ruangan ini sedang digunakan pada waktu tersebut. Silakan pilih waktu atau ruangan lain.']);
                }

                $lockedAppointment->update([
                    ...$data,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                ]);

                $this->syncPendingTherapyInvoices($previousPatientId);
                if ($previousPatientId !== (int) $lockedAppointment->patient_id) {
                    $this->syncPendingTherapyInvoices((int) $lockedAppointment->patient_id);
                }
            });
        } catch (QueryException $exception) {
            $this->handleSchedulingConstraintViolation($exception);
        }

        return redirect()->route('scheduling.index')->with('success', 'Jadwal terapi berhasil diperbarui.');
    }

    public function cancel(Request $request, Appointment $appointment): RedirectResponse
    {
        DB::transaction(function () use ($appointment, $request): void {
            Sale::query()->where('appointment_id', $appointment->id)->lockForUpdate()->first();
            $lockedAppointment = Appointment::query()->whereKey($appointment->id)->lockForUpdate()->firstOrFail();

            if ($lockedAppointment->status === 'cancelled') {
                return;
            }

            if (! in_array($lockedAppointment->status, ['scheduled', 'confirmed'], true)) {
                throw ValidationException::withMessages([
                    'cancel' => 'Hanya jadwal terjadwal atau terkonfirmasi yang dapat dibatalkan.',
                ]);
            }

            $this->restoreAppointmentPackageUsage($lockedAppointment, $request);

            $lockedAppointment->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_note' => 'Dibatalkan dari halaman penjadwalan.',
            ]);

            $this->syncPendingTherapyInvoices((int) $lockedAppointment->patient_id);
        });

        return redirect()->route('scheduling.index')->with('success', 'Jadwal dibatalkan. Kuota paket yang sudah terpakai dikembalikan.');
    }

    private function hasAppointmentConflict(string $resourceColumn, int $resourceId, Carbon $startsAt, Carbon $endsAt, ?int $ignoreAppointmentId = null): bool
    {
        $appointmentConflict = Appointment::query()
            ->where('status', '!=', 'cancelled')
            ->where($resourceColumn, $resourceId)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->when($ignoreAppointmentId !== null, fn ($query) => $query->whereKeyNot($ignoreAppointmentId))
            ->exists();

        if ($resourceColumn !== 'therapist_profile_id' || $appointmentConflict) {
            return $appointmentConflict;
        }

        return Assessment::query()
            ->where('therapist_profile_id', $resourceId)
            ->whereIn('status', ['awaiting_payment', 'paid', 'scheduled'])
            ->where('scheduled_at', '<', $endsAt)
            ->where('scheduled_at', '>', $startsAt->copy()->subMinutes(Assessment::SCHEDULE_DURATION_MINUTES))
            ->exists();
    }

    private function therapistAssignmentRule(Request $request): Exists
    {
        $therapyCode = TherapyType::query()->whereKey($request->input('therapy_type_id'))->value('code');

        if (! in_array($therapyCode, ['TO', 'TW'], true)) {
            return Rule::exists('therapist_profiles', 'id');
        }

        return Rule::exists('patient_therapists', 'therapist_profile_id')->where(fn (Builder $query) => $query
            ->where('patient_id', $request->input('patient_id'))
            ->where('therapy_type_id', $request->input('therapy_type_id')));
    }

    /**
     * @param  array{therapist_profile_id: int|string, room_id: int|string}  $data
     */
    private function lockSchedulingResources(array $data): void
    {
        TherapistProfile::query()
            ->whereKey($data['therapist_profile_id'])
            ->lockForUpdate()
            ->firstOrFail();

        Room::query()
            ->whereKey($data['room_id'])
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function handleSchedulingConstraintViolation(QueryException $exception): never
    {
        if (($exception->errorInfo[0] ?? null) === '23P01') {
            throw ValidationException::withMessages(['starts_at' => 'Jadwal bentrok: terapis atau ruangan baru saja dipesan pada waktu tersebut. Silakan pilih waktu lain.']);
        }

        throw $exception;
    }

    private function restoreAppointmentPackageUsage(Appointment $appointment, Request $request): void
    {
        foreach ([
            ['ledger' => 'duration_package_ledger', 'entitlements' => 'duration_package_entitlements', 'foreign_key' => 'duration_package_entitlement_id'],
            ['ledger' => 'package_ledger', 'entitlements' => 'package_entitlements', 'foreign_key' => 'package_entitlement_id'],
        ] as $ledgerDefinition) {
            $debits = DB::table($ledgerDefinition['ledger'])
                ->where('appointment_id', $appointment->id)
                ->where('quantity_delta', '<', 0)
                ->whereNull('reversal_of_id')
                ->lockForUpdate()
                ->get();

            foreach ($debits as $debit) {
                $alreadyReversed = DB::table($ledgerDefinition['ledger'])
                    ->where('reversal_of_id', $debit->id)
                    ->exists();

                if ($alreadyReversed) {
                    continue;
                }

                $entitlement = DB::table($ledgerDefinition['entitlements'])
                    ->where('id', $debit->{$ledgerDefinition['foreign_key']})
                    ->lockForUpdate()
                    ->first();

                if ($entitlement === null) {
                    continue;
                }

                DB::table($ledgerDefinition['entitlements'])
                    ->where('id', $entitlement->id)
                    ->increment('remaining_sessions', abs((int) $debit->quantity_delta));

                DB::table($ledgerDefinition['ledger'])->insert([
                    $ledgerDefinition['foreign_key'] => $entitlement->id,
                    'appointment_id' => null,
                    'reversal_of_id' => $debit->id,
                    'quantity_delta' => abs((int) $debit->quantity_delta),
                    'reason' => 'appointment_cancelled',
                    'created_by' => $request->user()->id,
                    'created_at' => now(),
                ]);

                $this->reactivatePackageIfAvailable((int) $entitlement->patient_package_id);
            }
        }
    }

    private function reactivatePackageIfAvailable(int $patientPackageId): void
    {
        $patientPackage = DB::table('patient_packages')->where('id', $patientPackageId)->lockForUpdate()->first();

        if ($patientPackage === null || $patientPackage->status !== 'completed' || Carbon::parse($patientPackage->expires_at)->isPast()) {
            return;
        }

        $remainingSessions = (int) DB::table('duration_package_entitlements')
            ->where('patient_package_id', $patientPackageId)
            ->sum('remaining_sessions');
        $remainingSessions += (int) DB::table('package_entitlements')
            ->where('patient_package_id', $patientPackageId)
            ->sum('remaining_sessions');

        if ($remainingSessions === 0) {
            return;
        }

        $hasOtherActivePackage = DB::table('patient_packages')
            ->where('patient_id', $patientPackage->patient_id)
            ->where('status', 'active')
            ->where('id', '!=', $patientPackageId)
            ->exists();

        if (! $hasOtherActivePackage) {
            DB::table('patient_packages')
                ->where('id', $patientPackageId)
                ->update(['status' => 'active', 'updated_at' => now()]);
        }
    }

    private function syncPendingTherapyInvoices(int $patientId): void
    {
        Sale::query()
            ->where('patient_id', $patientId)
            ->where('status', 'pending')
            ->whereNotNull('appointment_id')
            ->whereHas('appointment', fn ($query) => $query->whereNotIn('status', ['scheduled', 'confirmed', 'in_progress']))
            ->update(['status' => 'cancelled']);

        Appointment::query()
            ->where('patient_id', $patientId)
            ->whereIn('status', ['scheduled', 'confirmed', 'in_progress'])
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get()
            ->each(function (Appointment $appointment): void {
                $sale = Sale::query()
                    ->where('appointment_id', $appointment->id)
                    ->lockForUpdate()
                    ->first();

                if ($sale?->status === 'paid') {
                    return;
                }

                if ($appointment->hasAvailablePackageCoverage()) {
                    $sale?->update(['status' => 'cancelled']);

                    return;
                }

                $service = DB::table('service_catalog')
                    ->where('service_type', 'session')
                    ->where('therapy_type_id', $appointment->therapy_type_id)
                    ->where('is_active', true)
                    ->orderBy('id')
                    ->first();

                if ($service === null) {
                    throw ValidationException::withMessages([
                        'therapy_type_id' => 'Belum ada tarif sesi aktif untuk jenis terapi ini. Tambahkan tarif sesi sebelum membuat jadwal tanpa paket.',
                    ]);
                }

                $price = (int) $service->price;
                if ($price < 1) {
                    throw ValidationException::withMessages([
                        'therapy_type_id' => 'Tarif sesi terapi harus lebih dari nol untuk membuat tagihan.',
                    ]);
                }

                if ($sale === null) {
                    $sale = Sale::create([
                        'invoice_number' => 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                        'patient_id' => $appointment->patient_id,
                        'appointment_id' => $appointment->id,
                        'cashier_id' => null,
                        'status' => 'pending',
                        'subtotal' => $price,
                        'discount_total' => 0,
                        'grand_total' => $price,
                        'paid_at' => null,
                    ]);
                } else {
                    $sale->update([
                        'patient_id' => $appointment->patient_id,
                        'status' => 'pending',
                        'subtotal' => $price,
                        'discount_total' => 0,
                        'grand_total' => $price,
                        'paid_at' => null,
                    ]);
                }

                $lineData = [
                    'line_type' => 'session',
                    'package_catalog_id' => null,
                    'service_catalog_id' => $service->id,
                    'product_id' => null,
                    'description' => $service->name,
                    'quantity' => 1,
                    'unit_price' => $price,
                    'line_total' => $price,
                ];
                $saleLine = $sale->lines()->first();

                if ($saleLine === null) {
                    $sale->lines()->create($lineData);
                } else {
                    $saleLine->update($lineData);
                }
            });
    }

    private function ensurePatientEvaluationIsComplete(int $patientId): void
    {
        $latestEvaluationAt = DB::table('assessments')
            ->where('patient_id', $patientId)
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->max('completed_at');
        $completedSessions = Appointment::query()
            ->where('patient_id', $patientId)
            ->where('status', 'completed')
            ->where('attendance_status', 'present')
            ->whereHas('report', fn ($query) => $query->where('status', 'submitted'))
            ->when($latestEvaluationAt !== null, fn ($query) => $query->where('starts_at', '>', $latestEvaluationAt))
            ->count();

        if ($completedSessions >= self::EVALUATION_SESSION_INTERVAL) {
            throw ValidationException::withMessages([
                'patient_id' => 'Pasien sudah mencapai 25 sesi. Selesaikan evaluasi sebelum membuat atau mengubah jadwal terapi.',
            ]);
        }
    }

    /**
     * @param  array{patient_id: int|string, therapy_type_id: int|string}  $data
     */
    private function durationForAppointment(array $data, Carbon $startsAt, ?int $ignoreAppointmentId = null): int
    {
        $patientPackages = DB::table('patient_packages')
            ->where('patient_id', $data['patient_id'])
            ->where('status', 'active')
            ->where('starts_at', '<=', $startsAt)
            ->where('expires_at', '>=', $startsAt)
            ->orderByDesc('starts_at')
            ->lockForUpdate()
            ->get(['id', 'starts_at', 'expires_at']);

        foreach ($patientPackages as $patientPackage) {
            $bookedAppointments = Appointment::query()
                ->where('patient_id', $data['patient_id'])
                ->whereIn('status', ['scheduled', 'confirmed', 'in_progress'])
                ->where('starts_at', '>=', $patientPackage->starts_at)
                ->where('starts_at', '<=', $patientPackage->expires_at)
                ->when($ignoreAppointmentId !== null, fn ($query) => $query->whereKeyNot($ignoreAppointmentId))
                ->get(['therapy_type_id', 'starts_at', 'ends_at']);

            $durationEntitlements = DB::table('duration_package_entitlements')
                ->where('patient_package_id', $patientPackage->id)
                ->orderBy('duration_minutes')
                ->get(['duration_minutes', 'remaining_sessions']);

            if ($durationEntitlements->isNotEmpty()) {
                foreach ($durationEntitlements as $entitlement) {
                    $bookedSessions = $bookedAppointments->filter(function (Appointment $appointment) use ($entitlement): bool {
                        return (int) round($appointment->starts_at->diffInMinutes($appointment->ends_at)) === (int) $entitlement->duration_minutes;
                    })->count();

                    if ($bookedSessions < (int) $entitlement->remaining_sessions) {
                        return (int) $entitlement->duration_minutes;
                    }
                }

                continue;
            }

            $legacyEntitlement = DB::table('package_entitlements')
                ->where('patient_package_id', $patientPackage->id)
                ->where('therapy_type_id', $data['therapy_type_id'])
                ->where('remaining_sessions', '>', 0)
                ->first(['therapy_type_id', 'remaining_sessions']);

            if ($legacyEntitlement !== null) {
                $bookedSessions = $bookedAppointments->where('therapy_type_id', (int) $data['therapy_type_id'])->count();

                if ($bookedSessions < (int) $legacyEntitlement->remaining_sessions) {
                    return (int) TherapyType::whereKey($data['therapy_type_id'])->value('default_duration_minutes');
                }
            }
        }

        return (int) TherapyType::whereKey($data['therapy_type_id'])->value('default_duration_minutes');
    }
}
