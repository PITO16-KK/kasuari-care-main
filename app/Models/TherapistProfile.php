<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TherapistProfile extends Model
{
    protected $fillable = ['user_id', 'license_number', 'specialty'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function patients(): BelongsToMany
    {
        return $this->belongsToMany(Patient::class, 'patient_therapists')
            ->withPivot('therapy_type_id')
            ->withTimestamps();
    }
}
