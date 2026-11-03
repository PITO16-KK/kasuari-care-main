<?php

namespace App\Models;

use Database\Factories\PatientIntakeSubmissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array{child_name?: string|null, birth_date?: string|null, current_address?: string|null}|null $child_data
 * @property array{contact_details?: string|list<string>|null, occupation?: string|list<string>|null}|null $father_data
 * @property array{contact_details?: string|list<string>|null, occupation?: string|list<string>|null}|null $mother_data
 * @property array{siblings?: string|list<string>|null, lives_with_parents?: string|list<string>|null, primary_companions?: string|list<string>|null, family_history?: string|list<string>|null, referral_source?: string|list<string>|null}|null $family_context
 * @property array{daily_language?: string|list<string>|null, independence_skills?: string|list<string>|null, communicates_needs?: string|list<string>|null, parent_perceived_causes?: string|list<string>|null, concerns_first_noticed?: string|list<string>|null, previous_therapies?: string|list<string>|null, feeding?: string|list<string>|null, communication_and_storytelling?: string|list<string>|null, speech_clarity?: string|list<string>|null, attention_duration?: string|list<string>|null, reading_and_writing?: string|list<string>|null, understands_simple_instructions?: string|list<string>|null, tantrums_and_triggers?: string|list<string>|null, walking?: string|list<string>|null, crawling?: string|list<string>|null}|null $development_history
 * @property array{previous_exam?: string|list<string>|null, previous_exam_provider?: string|list<string>|null, doctor_or_psychologist_diagnosis?: string|list<string>|null, medical_conditions?: string|list<string>|null}|null $health_history
 */
class PatientIntakeSubmission extends Model
{
    /** @use HasFactory<PatientIntakeSubmissionFactory> */
    use HasFactory;

    protected $fillable = [
        'source', 'response_id', 'submitted_at', 'child_data', 'father_data', 'mother_data',
        'family_context', 'development_history', 'health_history', 'verification_status', 'patient_id',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'child_data' => 'array',
            'father_data' => 'array',
            'mother_data' => 'array',
            'family_context' => 'array',
            'development_history' => 'array',
            'health_history' => 'array',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
