<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionReport extends Model
{
    protected $hidden = ['photo_path'];

    protected $fillable = [
        'appointment_id', 'therapist_user_id', 'status', 'activities', 'parent_notes', 'homework', 'photo_path', 'submitted_at',
        'edited_at', 'edited_by_user_id',
    ];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'edited_at' => 'datetime'];
    }

    public function editedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by_user_id');
    }

    public function scores()
    {
        return $this->hasMany(SessionGoalScore::class);
    }
}
