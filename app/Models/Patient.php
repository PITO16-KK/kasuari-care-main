<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

class Patient extends Model
{
    protected $fillable = [
        'patient_code', 'name', 'birth_date', 'gender', 'address', 'primary_whatsapp',
        'main_complaint', 'school_name', 'medical_history', 'milestones', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'milestones' => 'array', 'is_active' => 'boolean'];
    }

    public static function nextCode(): string
    {
        $year = (int) now()->format('Y');

        DB::table('patient_code_counters')->upsert(
            [['year' => $year, 'last_number' => 0]],
            ['year'],
            ['year'],
        );

        $counter = DB::table('patient_code_counters')->where('year', $year)->lockForUpdate()->first();
        $next = $counter->last_number + 1;
        DB::table('patient_code_counters')->where('year', $year)->update(['last_number' => $next]);

        return sprintf('KL-%d-%03d', $year, $next);
    }

    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(ParentContact::class, 'patient_parents', 'patient_id', 'parent_id')
            ->withPivot(['relation', 'is_primary_contact', 'can_access_portal']);
    }

    public function therapists(): BelongsToMany
    {
        return $this->belongsToMany(TherapistProfile::class, 'patient_therapists')
            ->withPivot('therapy_type_id')
            ->withTimestamps();
    }

    /** @param array<string, int|string|null> $assignments */
    public function syncTherapistAssignments(array $assignments): void
    {
        $therapyTypeIds = TherapyType::query()
            ->whereIn('code', array_keys($assignments))
            ->pluck('id', 'code');
        $therapists = [];

        foreach ($assignments as $code => $therapistProfileId) {
            if (blank($therapistProfileId) || ! $therapyTypeIds->has($code)) {
                continue;
            }

            $therapists[(int) $therapistProfileId] = [
                'therapy_type_id' => (int) $therapyTypeIds->get($code),
            ];
        }

        $this->therapists()->sync($therapists);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function assessments()
    {
        return $this->hasMany(Assessment::class);
    }

    public function packages()
    {
        return $this->hasMany(PatientPackage::class);
    }
}
