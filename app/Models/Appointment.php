<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Appointment extends Model
{
    protected $fillable = [
        'patient_id', 'therapy_type_id', 'therapist_profile_id', 'room_id', 'assessment_id',
        'starts_at', 'ends_at', 'status', 'booking_source', 'attendance_status', 'created_by',
        'cancelled_at', 'cancellation_note',
    ];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function therapyType()
    {
        return $this->belongsTo(TherapyType::class);
    }

    public function therapist()
    {
        return $this->belongsTo(TherapistProfile::class, 'therapist_profile_id');
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function report()
    {
        return $this->hasOne(SessionReport::class);
    }

    public function sale(): HasOne
    {
        return $this->hasOne(Sale::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function hasAvailablePackageCoverage(): bool
    {
        $packages = DB::table('patient_packages')
            ->where('patient_id', $this->patient_id)
            ->where('status', 'active')
            ->where('starts_at', '<=', $this->starts_at)
            ->where('expires_at', '>=', $this->starts_at)
            ->orderByDesc('starts_at')
            ->get(['id', 'starts_at', 'expires_at']);
        $durationMinutes = (int) round($this->starts_at->diffInMinutes($this->ends_at));

        foreach ($packages as $package) {
            $bookedAppointments = self::query()
                ->where('patient_id', $this->patient_id)
                ->whereIn('status', ['scheduled', 'confirmed', 'in_progress'])
                ->whereBetween('starts_at', [$package->starts_at, $package->expires_at])
                ->get(['id', 'therapy_type_id', 'starts_at', 'ends_at']);
            $durationEntitlements = DB::table('duration_package_entitlements')
                ->where('patient_package_id', $package->id)
                ->where('remaining_sessions', '>', 0)
                ->orderBy('duration_minutes')
                ->get(['duration_minutes', 'remaining_sessions']);

            foreach ($durationEntitlements as $entitlement) {
                if ((int) $entitlement->duration_minutes !== $durationMinutes) {
                    continue;
                }

                $matchingAppointments = $bookedAppointments->filter(
                    fn (self $appointment): bool => (int) round($appointment->starts_at->diffInMinutes($appointment->ends_at)) === $durationMinutes,
                )->sort(function (self $first, self $second): int {
                    return $first->starts_at->getTimestamp() <=> $second->starts_at->getTimestamp()
                        ?: $first->id <=> $second->id;
                })->values();
                $position = $matchingAppointments->search(fn (self $appointment): bool => (int) $appointment->id === (int) $this->id);

                if ($position !== false && $position < (int) $entitlement->remaining_sessions) {
                    return true;
                }
            }

            $legacyEntitlements = DB::table('package_entitlements')
                ->where('patient_package_id', $package->id)
                ->where('therapy_type_id', $this->therapy_type_id)
                ->where('remaining_sessions', '>', 0)
                ->get(['remaining_sessions']);

            foreach ($legacyEntitlements as $entitlement) {
                $matchingAppointments = $bookedAppointments->where('therapy_type_id', $this->therapy_type_id)
                    ->sort(function (self $first, self $second): int {
                        return $first->starts_at->getTimestamp() <=> $second->starts_at->getTimestamp()
                            ?: $first->id <=> $second->id;
                    })->values();
                $position = $matchingAppointments->search(fn (self $appointment): bool => (int) $appointment->id === (int) $this->id);

                if ($position !== false && $position < (int) $entitlement->remaining_sessions) {
                    return true;
                }
            }
        }

        return false;
    }
}
