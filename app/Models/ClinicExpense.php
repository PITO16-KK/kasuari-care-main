<?php

namespace App\Models;

use Database\Factories\ClinicExpenseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicExpense extends Model
{
    /** @use HasFactory<ClinicExpenseFactory> */
    use HasFactory;

    protected $fillable = ['name', 'amount', 'expense_date', 'proof_path', 'created_by'];

    protected $hidden = ['proof_path'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'expense_date' => 'date'];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
