<?php

namespace App\Models;

use Database\Factories\TherapistPayrollFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TherapistPayroll extends Model
{
    /** @use HasFactory<TherapistPayrollFactory> */
    use HasFactory;

    protected $fillable = ['therapist_profile_id', 'payroll_period', 'amount', 'payment_date', 'proof_path', 'created_by'];

    protected $hidden = ['proof_path'];

    protected function casts(): array
    {
        return [
            'payroll_period' => 'date',
            'amount' => 'integer',
            'payment_date' => 'date',
        ];
    }

    public function therapist(): BelongsTo
    {
        return $this->belongsTo(TherapistProfile::class, 'therapist_profile_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
