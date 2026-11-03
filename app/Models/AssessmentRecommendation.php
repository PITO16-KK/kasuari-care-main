<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentRecommendation extends Model
{
    protected $fillable = ['assessment_id', 'therapy_type_id', 'sessions_per_week', 'duration_minutes', 'notes'];

    public function therapyType()
    {
        return $this->belongsTo(TherapyType::class);
    }
}