<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageCatalog extends Model
{
    protected $table = 'package_catalog';

    protected $fillable = [
        'name',
        'validity_days',
        'price',
        'session_duration_minutes',
        'session_quantity',
        'is_active',
    ];
}
