<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TherapyType extends Model
{
    protected $fillable = ['code', 'name', 'default_duration_minutes', 'is_active'];
}