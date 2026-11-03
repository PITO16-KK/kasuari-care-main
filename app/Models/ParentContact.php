<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParentContact extends Model
{
    protected $table = 'parents';

    protected $fillable = ['user_id', 'name', 'phone', 'whatsapp', 'email', 'address'];

    public function patients()
    {
        return $this->belongsToMany(Patient::class, 'patient_parents', 'parent_id', 'patient_id')
            ->withPivot(['relation', 'is_primary_contact', 'can_access_portal']);
    }
}