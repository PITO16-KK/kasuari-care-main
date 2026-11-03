<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PatientPackage extends Model
{
    protected $fillable = ['patient_id', 'package_catalog_id', 'sale_id', 'status', 'starts_at', 'expires_at'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function entitlements()
    {
        return $this->hasMany(PackageEntitlement::class);
    }

    public function catalog()
    {
        return $this->belongsTo(PackageCatalog::class, 'package_catalog_id');
    }
}