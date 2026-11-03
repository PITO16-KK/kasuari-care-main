<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Sale;
use App\Models\SessionReport;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SessionReportController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $this->filters($request);
        $appointments = $this->filteredAppointments($filters, $user)
            ->with([
                'patient:id,patient_code,name',
                'therapyType:id,code,name',
                'therapist.user:id,name',
                'room:id,name',
                'assessment:id,patient_id,scheduled_at',
                'report.editedBy:id,name',
                'sale.lines',
            ])
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        foreach ($appointments as $appointment) {
            $appointment->can_submit_report = $appointment->report?->status === 'submitted'
                ? false
                : ($this->findReportQuotaEntitlement($appointment) !== null
                    || ($appointment->sale?->status === 'paid'
                        && $appointment->sale->lines->contains('line_type', 'session')));

            if ($appointment->report !== null) {
                $appointment->report->makeHidden('photo_path');

                if ($appointment->report->photo_path !== null) {
                    $photoUrl = route('sessions.report.photo', $appointment, false);
                    $photoVersion = hash('sha256', $appointment->report->photo_path);

                    $appointment->report->setAttribute('photo_url', "{$photoUrl}?v={$photoVersion}");
                }
            }
        }

        return Inertia::render('Sessions/Index', [
            'filters' => $filters,
            'appointments' => $appointments,
            'therapists' => $user->hasRole('owner')
                ? TherapistProfile::query()->with('user:id,name')->get(['id', 'user_id'])->sortBy('user.name')->values()
                : [],
            'canFilterTherapist' => $user->hasRole('owner'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $appointments = $this->filteredAppointments($filters, $request->user())
            ->whereHas('report', fn (Builder $query) => $query->where('status', 'submitted'))
            ->with([
                'patient:id,patient_code,name',
                'therapyType:id,name',
                'therapist.user:id,name',
                'room:id,name',
                'report.editedBy:id,name',
            ])
            ->orderByDesc('starts_at')
            ->orderByDesc('id');
        $reference = $filters['period'] === 'monthly'
            ? str_replace('-', '', $filters['month'])
            : str_replace('-', '', $filters['date']);
        $therapistReference = $filters['therapist_id'] !== '' ? '-terapis-'.$filters['therapist_id'] : '';
        $filename = "laporan-sesi-{$filters['period']}-{$reference}{$therapistReference}.csv";

        return response()->streamDownload(function () use ($appointments): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Tanggal sesi (WIB)', 'Kode pasien', 'Nama pasien', 'Jenis terapi', 'Terapis ditugaskan', 'Ruangan', 'Aktivitas sesi', 'Catatan orang tua', 'Latihan di rumah', 'Laporan dikirim (WIB)', 'Laporan diedit (WIB)', 'Diedit oleh'], escape: '');

            $appointments->chunk(500, function (EloquentCollection $appointmentBatch) use ($output): void {
                foreach ($appointmentBatch as $appointment) {
                    $report = $appointment->report;

                    fputcsv($output, [
                        $appointment->starts_at->copy()->setTimezone('Asia/Jakarta')->format('d-m-Y H:i').' WIB',
                        $this->spreadsheetSafeText($appointment->patient->patient_code),
                        $this->spreadsheetSafeText($appointment->patient->name),
                        $this->spreadsheetSafeText($appointment->therapyType->name),
                        $this->spreadsheetSafeText($appointment->therapist?->user?->name ?? 'Belum ditentukan'),
                        $this->spreadsheetSafeText($appointment->room?->name ?? 'Belum ditentukan'),
                        $this->spreadsheetSafeText($report->activities),
                        $this->spreadsheetSafeText($report->parent_notes),
                        $this->spreadsheetSafeText($report->homework),
                        $report->submitted_at?->copy()->setTimezone('Asia/Jakarta')->format('d-m-Y H:i').' WIB',
                        $report->edited_at
                            ? $report->edited_at->copy()->setTimezone('Asia/Jakarta')->format('d-m-Y H:i').' WIB'
                            : '',
                        $this->spreadsheetSafeText($report->editedBy?->name),
                    ], escape: '');
                }
            });

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function store(Request $request, Appointment $appointment): RedirectResponse
    {
        $data = $request->validate([
            'activities' => ['nullable', 'string', 'max:5000'],
            'parent_notes' => ['nullable', 'string', 'max:3000'],
            'homework' => ['nullable', 'string', 'max:3000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'scores' => ['nullable', 'array'],
            'scores.*.iep_goal_id' => ['required', 'exists:iep_goals,id'],
            'scores.*.score' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $newPhotoPath = null;
        $previousPhotoPath = null;

        try {
            DB::transaction(function () use ($request, $appointment, $data, &$newPhotoPath, &$previousPhotoPath): void {
                $lockedAppointment = Appointment::whereKey($appointment->id)->lockForUpdate()->firstOrFail();
                if ($lockedAppointment->status === 'cancelled') {
                    throw ValidationException::withMessages(['activities' => 'Sesi yang dibatalkan tidak dapat dilaporkan.']);
                }

                if ($lockedAppointment->starts_at->isFuture()) {
                    throw ValidationException::withMessages(['activities' => 'Laporan hanya dapat dikirim setelah waktu sesi dimulai.']);
                }

                if ($request->user()->hasRole('therapist')
                    && ! $request->user()->hasRole('owner')
                    && $lockedAppointment->therapist()->value('user_id') !== $request->user()->id) {
                    abort(403);
                }

                if (SessionReport::where('appointment_id', $lockedAppointment->id)->where('status', 'submitted')->exists()) {
                    throw ValidationException::withMessages(['activities' => 'Laporan sesi ini sudah pernah dikirim.']);
                }

                $reportEntitlement = $this->findReportQuotaEntitlement($lockedAppointment, true);
                $hasPaidSessionInvoice = Sale::query()
                    ->where('appointment_id', $lockedAppointment->id)
                    ->where('status', 'paid')
                    ->whereHas('lines', fn (Builder $query) => $query->where('line_type', 'session'))
                    ->exists();

                if ($hasPaidSessionInvoice) {
                    $reportEntitlement = null;
                }

                if ($reportEntitlement === null && ! $hasPaidSessionInvoice) {
                    $hasPendingInvoice = Sale::query()
                        ->where('appointment_id', $lockedAppointment->id)
                        ->where('status', 'pending')
                        ->exists();

                    $message = $hasPendingInvoice
                        ? 'Selesaikan pembayaran tagihan sesi terapi ini sebelum mengirim laporan.'
                        : 'Tidak tersedia slot kuota paket untuk sesi ini. Laporan hanya dapat dikirim sesuai jumlah sesi yang dibeli.';

                    throw ValidationException::withMessages(['activities' => $message]);
                }

                $previousPhotoPath = SessionReport::query()
                    ->where('appointment_id', $lockedAppointment->id)
                    ->value('photo_path');

                if ($request->hasFile('photo')) {
                    $storedPhotoPath = $request->file('photo')->store('session-reports', 'local');

                    if ($storedPhotoPath === false) {
                        throw new \RuntimeException('Foto laporan sesi tidak dapat disimpan.');
                    }

                    $newPhotoPath = $storedPhotoPath;
                }

                $reportData = [
                    'therapist_user_id' => $request->user()->id,
                    'status' => 'submitted',
                    'activities' => $data['activities'] ?? null,
                    'parent_notes' => $data['parent_notes'] ?? null,
                    'homework' => $data['homework'] ?? null,
                    'submitted_at' => now(),
                ];

                if ($newPhotoPath !== null) {
                    $reportData['photo_path'] = $newPhotoPath;
                }

                $report = SessionReport::updateOrCreate(
                    ['appointment_id' => $lockedAppointment->id],
                    $reportData,
                );

                foreach ($data['scores'] ?? [] as $score) {
                    $report->scores()->updateOrCreate(
                        ['iep_goal_id' => $score['iep_goal_id']],
                        ['score' => $score['score']],
                    );
                }

                if ($reportEntitlement !== null) {
                    if ($reportEntitlement['type'] === 'duration') {
                        DB::table('duration_package_entitlements')->where('id', $reportEntitlement['id'])->decrement('remaining_sessions');
                        DB::table('duration_package_ledger')->insert([
                            'duration_package_entitlement_id' => $reportEntitlement['id'],
                            'appointment_id' => $lockedAppointment->id,
                            'quantity_delta' => -1,
                            'reason' => 'session_completed',
                            'created_by' => $request->user()->id,
                            'created_at' => now(),
                        ]);
                    } else {
                        DB::table('package_entitlements')->where('id', $reportEntitlement['id'])->decrement('remaining_sessions');
                        DB::table('package_ledger')->insert([
                            'package_entitlement_id' => $reportEntitlement['id'],
                            'appointment_id' => $lockedAppointment->id,
                            'quantity_delta' => -1,
                            'reason' => 'session_completed',
                            'created_by' => $request->user()->id,
                            'created_at' => now(),
                        ]);
                    }

                    $this->markPackageCompletedIfExhausted($reportEntitlement['patient_package_id']);
                }

                $lockedAppointment->update(['status' => 'completed', 'attendance_status' => 'present']);
            });
        } catch (\Throwable $exception) {
            if ($newPhotoPath !== null) {
                Storage::disk('local')->delete($newPhotoPath);
            }

            throw $exception;
        }

        if ($newPhotoPath !== null && $previousPhotoPath !== null) {
            Storage::disk('local')->delete($previousPhotoPath);
        }

        return back()->with('success', 'Laporan sesi berhasil disimpan.');
    }

    public function update(Request $request, Appointment $appointment): RedirectResponse
    {
        $data = $request->validate([
            'activities' => ['nullable', 'string', 'max:5000'],
            'parent_notes' => ['nullable', 'string', 'max:3000'],
            'homework' => ['nullable', 'string', 'max:3000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_photo' => ['nullable', 'boolean'],
        ]);

        $newPhotoPath = null;
        $previousPhotoPath = null;
        $removePhoto = (bool) ($data['remove_photo'] ?? false);

        try {
            DB::transaction(function () use ($request, $appointment, $data, $removePhoto, &$newPhotoPath, &$previousPhotoPath): void {
                $lockedAppointment = Appointment::query()->whereKey($appointment->id)->lockForUpdate()->firstOrFail();

                if ($lockedAppointment->status === 'cancelled') {
                    throw ValidationException::withMessages(['activities' => 'Sesi yang dibatalkan tidak dapat diedit.']);
                }

                if ($request->user()->hasRole('therapist')
                    && ! $request->user()->hasRole('owner')
                    && $lockedAppointment->therapist()->value('user_id') !== $request->user()->id) {
                    abort(403);
                }

                $report = SessionReport::query()
                    ->where('appointment_id', $lockedAppointment->id)
                    ->where('status', 'submitted')
                    ->lockForUpdate()
                    ->firstOrFail();
                $previousPhotoPath = $report->photo_path;

                if ($request->hasFile('photo')) {
                    $storedPhotoPath = $request->file('photo')->store('session-reports', 'local');

                    if ($storedPhotoPath === false) {
                        throw new \RuntimeException('Foto laporan sesi tidak dapat disimpan.');
                    }

                    $newPhotoPath = $storedPhotoPath;
                }

                $before = [
                    'activities' => $report->activities,
                    'parent_notes' => $report->parent_notes,
                    'homework' => $report->homework,
                    'has_photo' => $report->photo_path !== null,
                ];

                $reportData = [
                    'activities' => $data['activities'] ?? null,
                    'parent_notes' => $data['parent_notes'] ?? null,
                    'homework' => $data['homework'] ?? null,
                    'edited_at' => now(),
                    'edited_by_user_id' => $request->user()->id,
                ];

                if ($newPhotoPath !== null) {
                    $reportData['photo_path'] = $newPhotoPath;
                } elseif ($removePhoto) {
                    $reportData['photo_path'] = null;
                }

                $report->update($reportData);

                DB::table('audit_logs')->insert([
                    'actor_id' => $request->user()->id,
                    'event' => 'session_report.updated',
                    'entity_type' => SessionReport::class,
                    'entity_id' => $report->id,
                    'before' => json_encode($before, JSON_THROW_ON_ERROR),
                    'after' => json_encode([
                        'activities' => $report->activities,
                        'parent_notes' => $report->parent_notes,
                        'homework' => $report->homework,
                        'has_photo' => $report->photo_path !== null,
                    ], JSON_THROW_ON_ERROR),
                    'ip_address' => $request->ip(),
                    'created_at' => now(),
                ]);
            });
        } catch (\Throwable $exception) {
            if ($newPhotoPath !== null) {
                Storage::disk('local')->delete($newPhotoPath);
            }

            throw $exception;
        }

        if (($newPhotoPath !== null || $removePhoto) && $previousPhotoPath !== null) {
            Storage::disk('local')->delete($previousPhotoPath);
        }

        return back()->with('success', 'Perubahan laporan sesi berhasil disimpan.');
    }

    public function photo(Request $request, Appointment $appointment): BinaryFileResponse
    {
        $report = $appointment->report()->where('status', 'submitted')->firstOrFail();

        if ($request->user()->hasRole('therapist')
            && ! $request->user()->hasRole('owner')
            && $appointment->therapist()->value('user_id') !== $request->user()->id) {
            abort(403);
        }

        abort_unless(
            $report->photo_path !== null && Storage::disk('local')->exists($report->photo_path),
            404,
        );

        $photoDisk = Storage::disk('local');

        $response = response()->file($photoDisk->path($report->photo_path), [
            'Content-Type' => $photoDisk->mimeType($report->photo_path),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);

        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }

    /**
     * @return array{type: 'duration'|'legacy', id: int, patient_package_id: int}|null
     */
    private function findReportQuotaEntitlement(Appointment $appointment, bool $lockForUpdate = false): ?array
    {
        $durationMinutes = (int) round($appointment->starts_at->diffInMinutes($appointment->ends_at));
        $durationEntitlements = DB::table('duration_package_entitlements')
            ->join('patient_packages', 'patient_packages.id', '=', 'duration_package_entitlements.patient_package_id')
            ->where('patient_packages.patient_id', $appointment->patient_id)
            ->where('patient_packages.status', 'active')
            ->where('patient_packages.starts_at', '<=', $appointment->starts_at)
            ->where('patient_packages.expires_at', '>=', $appointment->starts_at)
            ->where('duration_package_entitlements.duration_minutes', $durationMinutes)
            ->where('duration_package_entitlements.remaining_sessions', '>', 0)
            ->orderByDesc('patient_packages.starts_at')
            ->select([
                'duration_package_entitlements.id',
                'duration_package_entitlements.remaining_sessions',
                'patient_packages.id as patient_package_id',
                'patient_packages.starts_at as package_starts_at',
                'patient_packages.expires_at as package_expires_at',
            ])
            ->when($lockForUpdate, fn ($query) => $query->lockForUpdate())
            ->get();

        foreach ($durationEntitlements as $entitlement) {
            if ($this->appointmentPositionInPackage($appointment, $entitlement->package_starts_at, $entitlement->package_expires_at, $durationMinutes, null) <= (int) $entitlement->remaining_sessions) {
                return [
                    'type' => 'duration',
                    'id' => (int) $entitlement->id,
                    'patient_package_id' => (int) $entitlement->patient_package_id,
                ];
            }
        }

        if ($durationEntitlements->isNotEmpty()) {
            return null;
        }

        $legacyEntitlements = DB::table('package_entitlements')
            ->join('patient_packages', 'patient_packages.id', '=', 'package_entitlements.patient_package_id')
            ->where('patient_packages.patient_id', $appointment->patient_id)
            ->where('patient_packages.status', 'active')
            ->where('patient_packages.starts_at', '<=', $appointment->starts_at)
            ->where('patient_packages.expires_at', '>=', $appointment->starts_at)
            ->where('package_entitlements.therapy_type_id', $appointment->therapy_type_id)
            ->where('package_entitlements.remaining_sessions', '>', 0)
            ->orderByDesc('patient_packages.starts_at')
            ->select([
                'package_entitlements.id',
                'package_entitlements.remaining_sessions',
                'patient_packages.id as patient_package_id',
                'patient_packages.starts_at as package_starts_at',
                'patient_packages.expires_at as package_expires_at',
            ])
            ->when($lockForUpdate, fn ($query) => $query->lockForUpdate())
            ->get();

        foreach ($legacyEntitlements as $entitlement) {
            if ($this->appointmentPositionInPackage($appointment, $entitlement->package_starts_at, $entitlement->package_expires_at, null, (int) $appointment->therapy_type_id) <= (int) $entitlement->remaining_sessions) {
                return [
                    'type' => 'legacy',
                    'id' => (int) $entitlement->id,
                    'patient_package_id' => (int) $entitlement->patient_package_id,
                ];
            }
        }

        return null;
    }

    /**
     * @return array{period: string, date: string, month: string, therapist_id: int|string}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'period' => ['nullable', 'in:daily,weekly,monthly'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'month' => ['nullable', 'date_format:Y-m'],
            'therapist_id' => ['nullable', 'integer', 'exists:therapist_profiles,id'],
        ]);

        return [
            'period' => $validated['period'] ?? 'monthly',
            'date' => $validated['date'] ?? now('Asia/Jakarta')->toDateString(),
            'month' => $validated['month'] ?? now('Asia/Jakarta')->format('Y-m'),
            'therapist_id' => $validated['therapist_id'] ?? '',
        ];
    }

    /**
     * @param  array{period: string, date: string, month: string, therapist_id: int|string}  $filters
     * @return Builder<Appointment>
     */
    private function filteredAppointments(array $filters, User $user): Builder
    {
        [$startsAt, $endsAt] = $this->periodRange($filters);

        return Appointment::query()
            ->where('starts_at', '>=', $startsAt)
            ->where('starts_at', '<', $endsAt)
            ->where('starts_at', '<=', now())
            ->whereIn('status', ['scheduled', 'confirmed', 'in_progress', 'completed'])
            ->when($user->hasRole('therapist') && ! $user->hasRole('owner'), function (Builder $query) use ($user): void {
                $query->whereHas('therapist', fn (Builder $therapistQuery) => $therapistQuery->where('user_id', $user->id));
            })
            ->when($user->hasRole('owner') && $filters['therapist_id'] !== '', fn (Builder $query) => $query->where('therapist_profile_id', $filters['therapist_id']));
    }

    /**
     * @param  array{period: string, date: string, month: string, therapist_id: int|string}  $filters
     * @return array{0: Carbon, 1: Carbon}
     */
    private function periodRange(array $filters): array
    {
        $startsAt = match ($filters['period']) {
            'weekly' => Carbon::parse($filters['date'], 'Asia/Jakarta')->startOfWeek(Carbon::MONDAY)->startOfDay(),
            'monthly' => Carbon::parse($filters['month'].'-01', 'Asia/Jakarta')->startOfMonth(),
            default => Carbon::parse($filters['date'], 'Asia/Jakarta')->startOfDay(),
        };
        $endsAt = match ($filters['period']) {
            'weekly' => $startsAt->copy()->addWeek(),
            'monthly' => $startsAt->copy()->addMonth(),
            default => $startsAt->copy()->addDay(),
        };

        return [$startsAt->copy()->utc(), $endsAt->copy()->utc()];
    }

    private function spreadsheetSafeText(?string $value): string
    {
        $value ??= '';

        return preg_match('/^\s*[=+\-@]/u', $value) === 1 ? "'{$value}" : $value;
    }

    private function appointmentPositionInPackage(
        Appointment $appointment,
        string $packageStartsAt,
        string $packageExpiresAt,
        ?int $durationMinutes,
        ?int $therapyTypeId,
    ): int {
        $appointments = Appointment::query()
            ->where('patient_id', $appointment->patient_id)
            ->whereIn('status', ['scheduled', 'confirmed', 'in_progress', 'completed'])
            ->where('starts_at', '>=', $packageStartsAt)
            ->where('starts_at', '<=', $packageExpiresAt)
            ->where(function (Builder $query) use ($appointment): void {
                $query->where('starts_at', '<', $appointment->starts_at)
                    ->orWhere(function (Builder $sameStartQuery) use ($appointment): void {
                        $sameStartQuery->where('starts_at', $appointment->starts_at)
                            ->where('id', '<=', $appointment->id);
                    });
            })
            ->whereDoesntHave('report', fn (Builder $query) => $query->where('status', 'submitted'))
            ->get(['id', 'therapy_type_id', 'starts_at', 'ends_at']);

        $matchingAppointments = $appointments->filter(function (Appointment $candidate) use ($durationMinutes, $therapyTypeId): bool {
            if ($durationMinutes !== null) {
                return (int) round($candidate->starts_at->diffInMinutes($candidate->ends_at)) === $durationMinutes;
            }

            return (int) $candidate->therapy_type_id === $therapyTypeId;
        });
        $currentAppointmentIsIncluded = $matchingAppointments->contains(fn (Appointment $candidate): bool => $candidate->id === $appointment->id);

        return $matchingAppointments->count() + ($currentAppointmentIsIncluded ? 0 : 1);
    }

    private function markPackageCompletedIfExhausted(int $patientPackageId): void
    {
        $remainingSessions = (int) DB::table('duration_package_entitlements')
            ->where('patient_package_id', $patientPackageId)
            ->sum('remaining_sessions');
        $remainingSessions += (int) DB::table('package_entitlements')
            ->where('patient_package_id', $patientPackageId)
            ->sum('remaining_sessions');

        if ($remainingSessions === 0) {
            DB::table('patient_packages')
                ->where('id', $patientPackageId)
                ->where('status', 'active')
                ->update(['status' => 'completed', 'updated_at' => now()]);
        }
    }
}
