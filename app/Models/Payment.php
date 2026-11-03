<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = ['sale_id', 'method', 'amount', 'reference_number', 'bank_name', 'proof_path', 'received_by', 'received_at'];

    protected $hidden = ['proof_path'];

    protected $appends = ['has_proof'];

    protected function casts(): array
    {
        return ['received_at' => 'datetime'];
    }

    public function getHasProofAttribute(): bool
    {
        return filled($this->proof_path);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
