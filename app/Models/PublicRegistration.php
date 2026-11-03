<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicRegistration extends Model
{
    protected $fillable = [
        'child_name', 'birth_date', 'gender', 'parent_name', 'parent_relation', 'whatsapp',
        'email', 'address', 'complaint', 'status', 'assigned_to', 'patient_id', 'follow_up_notes', 'submitted_at',
    ];

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'submitted_at' => 'datetime'];
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}