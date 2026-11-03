<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Assessment;
use App\Models\Patient;
use App\Models\Sale;
use App\Models\TherapistProfile;
use App\Models\TherapyType;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const EVALUATION_SESSION_INTERVAL = 25;

    private const DASHBOARD_NOTIFICATION_LIMIT = 5;

    private const PACKAGE_EXPIRY_ALERT_DAYS = 7;

    public function __invoke(Request $request): Response
    {
        $filters = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'therapist_id' => ['nullable', 'integer', 'exists:therapist_profiles,id'],
            'therapy_type_id' => ['nullable', 'integer', 'exists:therapy_types,id'],
        ]);
        $now = now();
        $today = $now->toDateString();
        $selectedDate = $filters['date'] ?? $today;
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $user = request()->user();
        $canSeeFinance = $user->hasAnyRole(['owner', 'finance']);
        $canHandleIntake = $user->hasAnyRole(['owner', 'front_office']);
        $canFilterTherapist = $user->hasAnyRole(['owner', 'finance', 'front_office']);
        $isTherapistDashboard = $user->hasRole('therapist')
            && ! $user->hasAnyRole(['owner', 'finance', 'front_office']);
        $canSeeUnpaidSessionAlerts = $canSeeFinance || $canHandleIntake;
        $schedule = Appointment::with(['patient:id,patient_code,name', 'therapyType:id,code,name', 'room:id,name', 'therapist.user:id,name'])
            ->whereDate('starts_at', $today)->where('status', '!=', 'cancelled');

        if ($isTherapistDashboard) {
            $schedule->whereHas('therapist', fn ($query) => $query->where('user_id', $user->id));
        }

        $therapyAppointments = $schedule->orderBy('starts_at')->get();
        $packageSessions = $this->packageSessionsFor($therapyAppointments);
        $todaySchedule = $therapyAppointments
            ->map(fn (Appointment $appointment): array => [
                'id' => 'therapy-'.$appointment->id,
                'agenda_type' => 'therapy',
                'starts_at' => $appointment->starts_at->toIso8601String(),
                'patient' => [
                    'name' => $appointment->patient->name,
                    'patient_code' => $appointment->patient->patient_code,
                ],
                'activity_name' => $appointment->therapyType->name,
                'therapist_name' => $appointment->therapist?->user?->name,
                'room_name' => $appointment->room?->name,
                'status' => $appointment->status,
                'package_session' => $packageSessions[$appointment->id] ?? null,
            ]);

        if (! $isTherapistDashboard) {
            $todayAssessments = Assessment::with([
                'patient:id,patient_code,name',
                'sale:id',
                'sale.lines:id,sale_id,description',
                'therapist.user:id,name',
            ])
                ->whereDate('scheduled_at', $today)
                ->whereIn('status', ['awaiting_payment', 'paid', 'scheduled'])
                ->orderBy('scheduled_at')
                ->get(['id', 'patient_id', 'sale_id', 'scheduled_at', 'status'])
                ->map(fn (Assessment $assessment): array => [
                    'id' => 'assessment-'.$assessment->id,
                    'agenda_type' => 'assessment',
                    'starts_at' => $assessment->scheduled_at->toIso8601String(),
                    'patient' => [
                        'name' => $assessment->patient->name,
                        'patient_code' => $assessment->patient->patient_code,
                    ],
                    'activity_name' => $assessment->sale?->lines->first()?->description ?? 'Assessment',
                    'therapist_name' => $assessment->therapist?->user?->name,
                    'room_name' => null,
                    'status' => $assessment->status,
                ]);

            $todaySchedule = $todaySchedule->concat($todayAssessments)
                ->sortBy(fn (array $entry): int => Carbon::parse($entry['starts_at'])->getTimestamp())
                ->values();
        }

        $selectedTherapyAppointmentsQuery = Appointment::with([
            'patient:id,patient_code,name',
            'therapyType:id,code,name',
            'therapist.user:id,name',
        ])
            ->whereDate('starts_at', $selectedDate)
            ->where('status', '!=', 'cancelled')
            ->when($isTherapistDashboard, fn (EloquentBuilder $query) => $query->whereHas(
                'therapist',
                fn (EloquentBuilder $therapistQuery) => $therapistQuery->where('user_id', $user->id),
            ))
            ->when(
                $canFilterTherapist && ! empty($filters['therapist_id']),
                fn (EloquentBuilder $query) => $query->where('therapist_profile_id', $filters['therapist_id']),
            )
            ->when(
                ! empty($filters['therapy_type_id']),
                fn (EloquentBuilder $query) => $query->where('therapy_type_id', $filters['therapy_type_id']),
            );
        $selectedTherapyAppointments = $selectedTherapyAppointmentsQuery->orderBy('starts_at')->get();
        $selectedPackageSessions = $this->packageSessionsFor($selectedTherapyAppointments);
        $therapySchedule = $selectedTherapyAppointments
            ->map(fn (Appointment $appointment): array => [
                'id' => $appointment->id,
                'starts_at' => $appointment->starts_at->toIso8601String(),
                'patient' => [
                    'id' => $appointment->patient->id,
                    'name' => $appointment->patient->name,
                    'patient_code' => $appointment->patient->patient_code,
                ],
                'therapy_type' => [
                    'code' => $appointment->therapyType->code,
                    'name' => $appointment->therapyType->name,
                ],
                'package_session' => $selectedPackageSessions[$appointment->id] ?? null,
                'therapist_name' => $appointment->therapist?->user?->name,
                'attendance_status' => $this->attendanceStatus($appointment),
            ])
            ->values();
        $otherAgenda = collect();

        if (! $isTherapistDashboard) {
            $otherAgenda = Assessment::with([
                'patient:id,patient_code,name',
                'sale:id',
                'sale.lines:id,sale_id,description',
                'therapist.user:id,name',
            ])
                ->whereDate('scheduled_at', $selectedDate)
                ->whereIn('status', ['awaiting_payment', 'paid', 'scheduled'])
                ->when(
                    $canFilterTherapist && ! empty($filters['therapist_id']),
                    fn (EloquentBuilder $query) => $query->whereHas(
                        'therapist',
                        fn (EloquentBuilder $therapistQuery) => $therapistQuery->where('user_id', $user->id),
                    ),
                )
                ->orderBy('scheduled_at')
                ->get(['id', 'patient_id', 'sale_id', 'therapist_profile_id', 'scheduled_at', 'status'])
                ->map(fn (Assessment $assessment): array => [
                    'id' => $assessment->id,
                    'starts_at' => $assessment->scheduled_at->toIso8601String(),
                    'patient_name' => $assessment->patient->name,
                    'patient_code' => $assessment->patient->patient_code,
                    'activity_name' => $assessment->sale?->lines->first()?->description ?? 'Assessment',
                    'therapist_name' => $assessment->therapist?->user?->name,
                    'status' => $assessment->status,
                ])
                ->values();
        }

        $evaluationAlerts = collect();

        if ($canHandleIntake || $isTherapistDashboard) {
            $latestAssessmentActivity = DB::table('assessments')
                ->select('patient_id')
                ->selectRaw('MAX(completed_at) as latest_evaluation_at')
                ->where('status', 'completed')
                ->whereNotNull('completed_at')
                ->groupBy('patient_id');

            $evaluationAlerts = Patient::query()
                ->join('appointments', 'appointments.patient_id', '=', 'patients.id')
                ->join('session_reports', 'session_reports.appointment_id', '=', 'appointments.id')
                ->leftJoinSub($latestAssessmentActivity, 'evaluation_activity', function ($join): void {
                    $join->on('evaluation_activity.patient_id', '=', 'patients.id');
                })
                ->where('patients.is_active', true)
                ->where('appointments.status', 'completed')
                ->where('appointments.attendance_status', 'present')
                ->where('session_reports.status', 'submitted')
                ->where(function ($query): void {
                    $query->whereNull('evaluation_activity.latest_evaluation_at')
                        ->orWhereColumn('appointments.starts_at', '>', 'evaluation_activity.latest_evaluation_at');
                })
                ->when($isTherapistDashboard, function ($query) use ($user): void {
                    $query->whereIn('appointments.therapist_profile_id', function ($therapistProfiles) use ($user): void {
                        $therapistProfiles->select('id')
                            ->from('therapist_profiles')
                            ->where('user_id', $user->id);
                    });
                })
                ->select(['patients.id', 'patients.patient_code', 'patients.name'])
                ->selectRaw('COUNT(appointments.id) as sessions_since_evaluation')
                ->groupBy('patients.id', 'patients.patient_code', 'patients.name')
                ->havingRaw('COUNT(appointments.id) >= ?', [self::EVALUATION_SESSION_INTERVAL])
                ->orderByDesc('sessions_since_evaluation')
                ->orderBy('patients.name')
                ->get();
        }

        $unpaidSessionAlerts = collect();
        $unpaidSessionAlertCount = 0;

        if ($canSeeUnpaidSessionAlerts) {
            $unpaidSessionQuery = Sale::query()
                ->select('sales.*')
                ->join('appointments', 'appointments.id', '=', 'sales.appointment_id')
                ->where('sales.status', 'pending')
                ->where('appointments.ends_at', '<=', $now)
                ->where('appointments.status', '!=', 'cancelled')
                ->whereHas('lines', fn (EloquentBuilder $query) => $query->where('line_type', 'session'));

            $unpaidSessionAlertCount = (clone $unpaidSessionQuery)->count();
            $unpaidSessionAlerts = (clone $unpaidSessionQuery)
                ->with([
                    'patient:id,patient_code,name',
                    'appointment:id,therapy_type_id,starts_at',
                    'appointment.therapyType:id,name',
                ])
                ->orderByDesc('appointments.ends_at')
                ->limit(self::DASHBOARD_NOTIFICATION_LIMIT)
                ->get(['sales.id', 'sales.invoice_number', 'sales.patient_id', 'sales.appointment_id', 'sales.grand_total'])
                ->map(fn (Sale $sale): array => [
                    'id' => $sale->id,
                    'invoice_number' => $sale->invoice_number,
                    'patient_name' => $sale->patient?->name,
                    'patient_code' => $sale->patient?->patient_code,
                    'therapy_name' => $sale->appointment?->therapyType?->name ?? 'Sesi terapi',
                    'starts_at' => $sale->appointment?->starts_at?->toIso8601String(),
                    'grand_total' => $canSeeFinance ? (int) $sale->grand_total : null,
                    'href' => $canSeeFinance
                        ? '/bills?type=therapy'
                        : '/patients?q='.urlencode($sale->patient?->patient_code ?? ''),
                ])
                ->values();
        }

        $expiringPackageAlerts = collect();
        $expiringPackageAlertCount = 0;

        if ($canHandleIntake) {
            $expiryDeadline = $now->copy()->addDays(self::PACKAGE_EXPIRY_ALERT_DAYS)->endOfDay();
            $expiringPackageQuery = DB::table('patient_packages')
                ->join('patients', 'patients.id', '=', 'patient_packages.patient_id')
                ->join('package_catalog', 'package_catalog.id', '=', 'patient_packages.package_catalog_id')
                ->where('patient_packages.status', 'active')
                ->where('patients.is_active', true)
                ->whereBetween('patient_packages.expires_at', [$now, $expiryDeadline])
                ->where(function (QueryBuilder $query): void {
                    $query->whereExists(function (QueryBuilder $entitlementQuery): void {
                        $entitlementQuery->selectRaw('1')
                            ->from('duration_package_entitlements')
                            ->whereColumn('duration_package_entitlements.patient_package_id', 'patient_packages.id')
                            ->where('duration_package_entitlements.remaining_sessions', '>', 0);
                    })->orWhereExists(function (QueryBuilder $entitlementQuery): void {
                        $entitlementQuery->selectRaw('1')
                            ->from('package_entitlements')
                            ->whereColumn('package_entitlements.patient_package_id', 'patient_packages.id')
                            ->where('package_entitlements.remaining_sessions', '>', 0);
                    });
                });

            $expiringPackageAlertCount = (clone $expiringPackageQuery)->count();
            $expiringPackageAlerts = (clone $expiringPackageQuery)
                ->orderBy('patient_packages.expires_at')
                ->limit(self::DASHBOARD_NOTIFICATION_LIMIT)
                ->get([
                    'patient_packages.id',
                    'patients.id as patient_id',
                    'patients.name as patient_name',
                    'patients.patient_code',
                    'package_catalog.name as package_name',
                    'patient_packages.expires_at',
                ])
                ->map(fn (object $package): array => [
                    'id' => (int) $package->id,
                    'patient_id' => (int) $package->patient_id,
                    'patient_name' => $package->patient_name,
                    'patient_code' => $package->patient_code,
                    'package_name' => $package->package_name,
                    'expires_at' => Carbon::parse($package->expires_at)->toIso8601String(),
                ])
                ->values();
        }

        $stats = $isTherapistDashboard
            ? [
                'todayAppointments' => $todaySchedule->count(),
                'todayPatients' => Appointment::query()
                    ->whereDate('starts_at', $today)
                    ->where('status', '!=', 'cancelled')
                    ->whereHas('therapist', fn (EloquentBuilder $query) => $query->where('user_id', $user->id))
                    ->distinct('patient_id')
                    ->count('patient_id'),
                'completedSessions' => Appointment::query()
                    ->whereDate('starts_at', $today)
                    ->where('status', 'completed')
                    ->whereHas('therapist', fn (EloquentBuilder $query) => $query->where('user_id', $user->id))
                    ->count(),
                'monthlyReports' => DB::table('session_reports')
                    ->join('appointments', 'appointments.id', '=', 'session_reports.appointment_id')
                    ->join('therapist_profiles', 'therapist_profiles.id', '=', 'appointments.therapist_profile_id')
                    ->where('therapist_profiles.user_id', $user->id)
                    ->where('session_reports.therapist_user_id', $user->id)
                    ->where('session_reports.status', 'submitted')
                    ->whereBetween('session_reports.submitted_at', [$monthStart, $monthEnd])
                    ->count(),
            ]
            : [
                'activePatients' => Patient::where('is_active', true)->count(),
                'todayAppointments' => $todaySchedule->count(),
                'completedSessions' => Appointment::whereDate('starts_at', $today)->where('status', 'completed')->count(),
                'monthlyCompletedSessions' => Appointment::query()
                    ->where('status', 'completed')
                    ->whereBetween('starts_at', [$monthStart, $monthEnd])
                    ->count(),
                'therapistsOnDuty' => Appointment::whereDate('starts_at', $today)->whereNotNull('therapist_profile_id')->distinct()->count('therapist_profile_id'),
            ];

        if ($canSeeFinance) {
            $stats['todayRevenue'] = Sale::whereDate('paid_at', $today)->where('status', 'paid')->sum('grand_total');
        }

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'isTherapistDashboard' => $isTherapistDashboard,
            'canSeeFinance' => $canSeeFinance,
            'canFilterTherapist' => $canFilterTherapist,
            'todaySchedule' => $todaySchedule,
            'therapySchedule' => $therapySchedule,
            'otherAgenda' => $otherAgenda,
            'scheduleFilters' => [
                'date' => $selectedDate,
                'therapist_id' => $canFilterTherapist ? ($filters['therapist_id'] ?? '') : '',
                'therapy_type_id' => $filters['therapy_type_id'] ?? '',
            ],
            'scheduleTherapists' => $canFilterTherapist
                ? TherapistProfile::with('user:id,name')->orderBy('id')->get(['id', 'user_id'])
                    ->map(fn (TherapistProfile $therapist): array => [
                        'id' => $therapist->id,
                        'name' => $therapist->user?->name,
                    ])
                : collect(),
            'scheduleTherapyTypes' => TherapyType::query()->orderBy('name')->get(['id', 'code', 'name']),
            'evaluationAlerts' => $evaluationAlerts,
            'evaluationAlertCount' => $evaluationAlerts->count(),
            'unpaidSessionAlerts' => $unpaidSessionAlerts,
            'unpaidSessionAlertCount' => $unpaidSessionAlertCount,
            'expiringPackageAlerts' => $expiringPackageAlerts,
            'expiringPackageAlertCount' => $expiringPackageAlertCount,
            'packageExpiryAlertDays' => self::PACKAGE_EXPIRY_ALERT_DAYS,
            'therapistCount' => TherapistProfile::count(),
        ]);
    }

    private function attendanceStatus(Appointment $appointment): string
    {
        if ($appointment->status === 'completed') {
            return 'completed';
        }

        return match ($appointment->attendance_status) {
            'present' => 'present',
            'absent', 'no_show', 'not_present' => 'absent',
            default => 'waiting',
        };
    }

    /**
     * @param  Collection<int, Appointment>  $appointments
     * @return array<int, array{number: int, total: int, package_name: string, package_id: int, package_number?: int}>
     */
    private function packageSessionsFor(Collection $appointments): array
    {
        if ($appointments->isEmpty()) {
            return [];
        }

        $submittedAppointmentIds = DB::table('session_reports')
            ->whereIn('appointment_id', $appointments->pluck('id'))
            ->where('status', 'submitted')
            ->pluck('appointment_id')
            ->map(fn ($appointmentId): int => (int) $appointmentId)
            ->all();
        $packageSessions = $this->submittedPackageSessionsFor($submittedAppointmentIds);
        $appointments = $appointments
            ->reject(fn (Appointment $appointment): bool => in_array((int) $appointment->id, $submittedAppointmentIds, true))
            ->values();

        if ($appointments->isEmpty()) {
            return $this->addPackageSequenceNumbers($packageSessions);
        }

        $patientIds = $appointments->pluck('patient_id')->unique()->all();
        $durationEntitlements = DB::table('duration_package_entitlements')
            ->join('patient_packages', 'patient_packages.id', '=', 'duration_package_entitlements.patient_package_id')
            ->join('package_catalog', 'package_catalog.id', '=', 'patient_packages.package_catalog_id')
            ->whereIn('patient_packages.patient_id', $patientIds)
            ->where('patient_packages.status', 'active')
            ->where('duration_package_entitlements.remaining_sessions', '>', 0)
            ->get([
                'duration_package_entitlements.patient_package_id',
                'duration_package_entitlements.duration_minutes',
                'duration_package_entitlements.granted_sessions',
                'duration_package_entitlements.remaining_sessions',
                'patient_packages.patient_id',
                'patient_packages.starts_at as package_starts_at',
                'patient_packages.expires_at as package_expires_at',
                'package_catalog.name as package_name',
            ])
            ->groupBy('patient_id');
        $legacyEntitlements = DB::table('package_entitlements')
            ->join('patient_packages', 'patient_packages.id', '=', 'package_entitlements.patient_package_id')
            ->join('package_catalog', 'package_catalog.id', '=', 'patient_packages.package_catalog_id')
            ->whereIn('patient_packages.patient_id', $patientIds)
            ->where('patient_packages.status', 'active')
            ->where('package_entitlements.remaining_sessions', '>', 0)
            ->get([
                'package_entitlements.patient_package_id',
                'package_entitlements.therapy_type_id',
                'package_entitlements.granted_sessions',
                'package_entitlements.remaining_sessions',
                'patient_packages.patient_id',
                'patient_packages.starts_at as package_starts_at',
                'patient_packages.expires_at as package_expires_at',
                'package_catalog.name as package_name',
            ])
            ->groupBy('patient_id');

        $packageDates = $durationEntitlements->flatten(1)->merge($legacyEntitlements->flatten(1));

        if ($packageDates->isEmpty()) {
            return $this->addPackageSequenceNumbers($packageSessions);
        }

        $packageAppointments = Appointment::query()
            ->whereIn('patient_id', $patientIds)
            ->whereIn('status', ['scheduled', 'confirmed', 'in_progress', 'completed'])
            ->whereBetween('starts_at', [
                $packageDates->min('package_starts_at'),
                $packageDates->max('package_expires_at'),
            ])
            ->whereDoesntHave('report', fn (EloquentBuilder $query) => $query->where('status', 'submitted'))
            ->get(['id', 'patient_id', 'therapy_type_id', 'starts_at', 'ends_at']);

        $upcomingPackageSessions = [];

        foreach ($appointments as $appointment) {
            $durationMinutes = (int) round($appointment->starts_at->diffInMinutes($appointment->ends_at));
            $patientDurationEntitlements = ($durationEntitlements->get($appointment->patient_id) ?? collect())
                ->filter(fn (object $entitlement): bool => (int) $entitlement->duration_minutes === $durationMinutes
                    && $entitlement->package_starts_at <= $appointment->starts_at
                    && $entitlement->package_expires_at >= $appointment->starts_at)
                ->sortByDesc('package_starts_at')
                ->values();

            foreach ($patientDurationEntitlements as $entitlement) {
                $position = $this->appointmentPositionInPackage(
                    $appointment,
                    $entitlement->package_starts_at,
                    $entitlement->package_expires_at,
                    $durationMinutes,
                    null,
                    $packageAppointments,
                );

                if ($position <= (int) $entitlement->remaining_sessions) {
                    $upcomingPackageSessions[$appointment->id] = [
                        'number' => (int) $entitlement->granted_sessions - (int) $entitlement->remaining_sessions + $position,
                        'total' => (int) $entitlement->granted_sessions,
                        'package_name' => $entitlement->package_name,
                        'package_id' => (int) $entitlement->patient_package_id,
                    ];
                    break;
                }
            }

            if (isset($upcomingPackageSessions[$appointment->id]) || $patientDurationEntitlements->isNotEmpty()) {
                continue;
            }

            $patientLegacyEntitlements = ($legacyEntitlements->get($appointment->patient_id) ?? collect())
                ->filter(fn (object $entitlement): bool => (int) $entitlement->therapy_type_id === (int) $appointment->therapy_type_id
                    && $entitlement->package_starts_at <= $appointment->starts_at
                    && $entitlement->package_expires_at >= $appointment->starts_at)
                ->sortByDesc('package_starts_at')
                ->values();

            foreach ($patientLegacyEntitlements as $entitlement) {
                $position = $this->appointmentPositionInPackage(
                    $appointment,
                    $entitlement->package_starts_at,
                    $entitlement->package_expires_at,
                    null,
                    (int) $appointment->therapy_type_id,
                    $packageAppointments,
                );

                if ($position <= (int) $entitlement->remaining_sessions) {
                    $upcomingPackageSessions[$appointment->id] = [
                        'number' => (int) $entitlement->granted_sessions - (int) $entitlement->remaining_sessions + $position,
                        'total' => (int) $entitlement->granted_sessions,
                        'package_name' => $entitlement->package_name,
                        'package_id' => (int) $entitlement->patient_package_id,
                    ];
                    break;
                }
            }
        }

        return $this->addPackageSequenceNumbers($packageSessions + $upcomingPackageSessions);
    }

    /**
     * @param  array<int, array{number: int, total: int, package_name: string, package_id: int, package_number?: int}>  $packageSessions
     * @return array<int, array{number: int, total: int, package_name: string, package_id: int, package_number?: int}>
     */
    private function addPackageSequenceNumbers(array $packageSessions): array
    {
        if ($packageSessions === []) {
            return [];
        }

        $targetPackages = DB::table('patient_packages')
            ->whereIn('id', collect($packageSessions)->pluck('package_id')->unique()->all())
            ->get(['id', 'patient_id']);
        $patientPackages = DB::table('patient_packages')
            ->whereIn('patient_id', $targetPackages->pluck('patient_id')->unique()->all())
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get(['id', 'patient_id'])
            ->groupBy('patient_id');
        $packageNumbers = [];

        foreach ($patientPackages as $packages) {
            foreach ($packages->values() as $index => $package) {
                $packageNumbers[(int) $package->id] = $index + 1;
            }
        }

        foreach ($packageSessions as &$packageSession) {
            $packageSession['package_number'] = $packageNumbers[$packageSession['package_id']] ?? null;
        }
        unset($packageSession);

        return $packageSessions;
    }

    /**
     * @param  array<int>  $appointmentIds
     * @return array<int, array{number: int, total: int, package_name: string, package_id: int, package_number?: int}>
     */
    private function submittedPackageSessionsFor(array $appointmentIds): array
    {
        if ($appointmentIds === []) {
            return [];
        }

        $durationDebits = DB::table('duration_package_ledger as ledger')
            ->join('duration_package_entitlements as entitlement', 'entitlement.id', '=', 'ledger.duration_package_entitlement_id')
            ->join('patient_packages', 'patient_packages.id', '=', 'entitlement.patient_package_id')
            ->join('package_catalog', 'package_catalog.id', '=', 'patient_packages.package_catalog_id')
            ->whereIn('ledger.appointment_id', $appointmentIds)
            ->where('ledger.quantity_delta', '<', 0)
            ->where('ledger.reason', 'session_completed')
            ->whereNotExists(function (QueryBuilder $query): void {
                $query->selectRaw('1')
                    ->from('duration_package_ledger as reversal')
                    ->whereColumn('reversal.reversal_of_id', 'ledger.id');
            })
            ->get([
                'ledger.id',
                'ledger.appointment_id',
                'ledger.duration_package_entitlement_id as entitlement_id',
                'entitlement.granted_sessions as total',
                'entitlement.patient_package_id as package_id',
                'package_catalog.name as package_name',
            ]);
        $packageSessions = $this->packageSessionsFromLedger(
            $durationDebits,
            'duration_package_ledger',
            'duration_package_entitlement_id',
        );

        $legacyDebits = DB::table('package_ledger as ledger')
            ->join('package_entitlements as entitlement', 'entitlement.id', '=', 'ledger.package_entitlement_id')
            ->join('patient_packages', 'patient_packages.id', '=', 'entitlement.patient_package_id')
            ->join('package_catalog', 'package_catalog.id', '=', 'patient_packages.package_catalog_id')
            ->whereIn('ledger.appointment_id', $appointmentIds)
            ->where('ledger.quantity_delta', '<', 0)
            ->where('ledger.reason', 'session_completed')
            ->whereNotExists(function (QueryBuilder $query): void {
                $query->selectRaw('1')
                    ->from('package_ledger as reversal')
                    ->whereColumn('reversal.reversal_of_id', 'ledger.id');
            })
            ->get([
                'ledger.id',
                'ledger.appointment_id',
                'ledger.package_entitlement_id as entitlement_id',
                'entitlement.granted_sessions as total',
                'entitlement.patient_package_id as package_id',
                'package_catalog.name as package_name',
            ]);

        return $packageSessions + $this->packageSessionsFromLedger(
            $legacyDebits,
            'package_ledger',
            'package_entitlement_id',
        );
    }

    /**
     * @param  Collection<int, object>  $debits
     * @return array<int, array{number: int, total: int, package_name: string, package_id: int, package_number?: int}>
     */
    private function packageSessionsFromLedger(Collection $debits, string $ledgerTable, string $entitlementColumn): array
    {
        if ($debits->isEmpty()) {
            return [];
        }

        $history = DB::table("{$ledgerTable} as ledger")
            ->whereIn("ledger.{$entitlementColumn}", $debits->pluck('entitlement_id')->unique()->all())
            ->where('ledger.quantity_delta', '<', 0)
            ->where('ledger.reason', 'session_completed')
            ->whereNotExists(function (QueryBuilder $query) use ($ledgerTable): void {
                $query->selectRaw('1')
                    ->from("{$ledgerTable} as reversal")
                    ->whereColumn('reversal.reversal_of_id', 'ledger.id');
            })
            ->orderBy('ledger.created_at')
            ->orderBy('ledger.id')
            ->get([
                'ledger.id',
                "ledger.{$entitlementColumn} as entitlement_id",
                'ledger.appointment_id',
            ])
            ->groupBy('entitlement_id');
        $packageSessions = [];

        foreach ($debits as $debit) {
            $sessionDebits = $history->get($debit->entitlement_id, collect())->values();
            $sessionIndex = $sessionDebits->search(
                fn (object $sessionDebit): bool => (int) $sessionDebit->id === (int) $debit->id,
            );

            if ($sessionIndex === false) {
                continue;
            }

            $packageSessions[(int) $debit->appointment_id] = [
                'number' => $sessionIndex + 1,
                'total' => (int) $debit->total,
                'package_name' => $debit->package_name,
                'package_id' => (int) $debit->package_id,
            ];
        }

        return $packageSessions;
    }

    private function appointmentPositionInPackage(
        Appointment $appointment,
        string $packageStartsAt,
        string $packageExpiresAt,
        ?int $durationMinutes,
        ?int $therapyTypeId,
        Collection $packageAppointments,
    ): int {
        $matchingAppointments = $packageAppointments->filter(function (Appointment $candidate) use (
            $appointment,
            $packageStartsAt,
            $packageExpiresAt,
            $durationMinutes,
            $therapyTypeId,
        ): bool {
            $candidateStartsAt = $candidate->starts_at;
            $appointmentStartsAt = $appointment->starts_at;
            $isAtOrBeforeAppointment = $candidateStartsAt < $appointmentStartsAt
                || ($candidateStartsAt->equalTo($appointmentStartsAt) && $candidate->id <= $appointment->id);

            if ((int) $candidate->patient_id !== (int) $appointment->patient_id
                || $candidateStartsAt < Carbon::parse($packageStartsAt)
                || $candidateStartsAt > Carbon::parse($packageExpiresAt)
                || ! $isAtOrBeforeAppointment) {
                return false;
            }

            if ($durationMinutes !== null) {
                return (int) round($candidateStartsAt->diffInMinutes($candidate->ends_at)) === $durationMinutes;
            }

            return (int) $candidate->therapy_type_id === $therapyTypeId;
        });
        $currentAppointmentIsIncluded = $matchingAppointments->contains(
            fn (Appointment $candidate): bool => (int) $candidate->id === (int) $appointment->id,
        );

        return $matchingAppointments->count() + ($currentAppointmentIsIncluded ? 0 : 1);
    }
}
