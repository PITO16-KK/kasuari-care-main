<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGoogleFormIntakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $answerGroups = [
            'child_data' => ['child_name', 'birth_date', 'current_address'],
            'father_data' => ['contact_details', 'occupation'],
            'mother_data' => ['contact_details', 'occupation'],
            'family_context' => ['siblings', 'lives_with_parents', 'primary_companions', 'family_history', 'referral_source'],
            'development_history' => [
                'daily_language', 'independence_skills', 'communicates_needs', 'parent_perceived_causes',
                'concerns_first_noticed', 'previous_therapies', 'feeding', 'communication_and_storytelling',
                'speech_clarity', 'attention_duration', 'reading_and_writing', 'understands_simple_instructions',
                'tantrums_and_triggers', 'walking', 'crawling',
            ],
            'health_history' => [
                'previous_exam', 'previous_exam_provider', 'doctor_or_psychologist_diagnosis', 'medical_conditions',
            ],
        ];

        $rules = [
            'source' => ['required', 'string', 'in:google_forms', 'max:100'],
            'response_id' => ['required', 'string', 'max:255'],
            'submitted_at' => ['nullable', 'date'],
        ];

        foreach ($answerGroups as $group => $keys) {
            $rules[$group] = ['sometimes', 'nullable', 'array:'.implode(',', $keys)];

            foreach ($keys as $key) {
                $rules["{$group}.{$key}"] = ['sometimes', 'nullable'];
            }
        }

        return $rules;
    }
}
