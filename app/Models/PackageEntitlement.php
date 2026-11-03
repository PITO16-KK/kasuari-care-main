<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageEntitlement extends Model
{
    public $timestamps = false;

    protected $fillable = ['patient_package_id', 'therapy_type_id', 'granted_sessions', 'remaining_sessions'];

    public function package()
    {
        return $this->belongsTo(PatientPackage::class, 'patient_package_id');
    }
}