<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assessment extends Model
{
    public const SCHEDULE_DURATION_MINUTES = 60;

    protected $fillable = [
        'patient_id', 'sale_id', 'therapist_profile_id', 'status', 'scheduled_at', 'completed_at', 'chief_complaint',
        'development_history', 'learning_behavior', 'summary', 'referral_notes', 'created_by',
    ];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'completed_at' => 'datetime', 'development_history' => 'array', 'learning_behavior' => 'array'];
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function therapist(): BelongsTo
    {
        return $this->belongsTo(TherapistProfile::class, 'therapist_profile_id');
    }

    public function recommendations()
    {
        return $this->hasMany(AssessmentRecommendation::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
