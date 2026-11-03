<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGoogleFormIntakeRequest;
use App\Models\PatientIntakeSubmission;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;

class GoogleFormIntakeWebhookController extends Controller
{
    public function __invoke(StoreGoogleFormIntakeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $responseId = $data['response_id'];
        $existingSubmission = PatientIntakeSubmission::query()
            ->where('response_id', $responseId)
            ->first();

        if ($existingSubmission !== null) {
            return response()->json(['status' => 'duplicate', 'response_id' => $responseId]);
        }

        $attributes = [
            'source' => $data['source'],
            'response_id' => $responseId,
            'submitted_at' => $data['submitted_at'] ?? null,
            'verification_status' => 'pending',
            'patient_id' => null,
        ];

        foreach ([
            'child_data', 'father_data', 'mother_data', 'family_context', 'development_history', 'health_history',
        ] as $group) {
            $attributes[$group] = $data[$group] ?? null;
        }

        try {
            PatientIntakeSubmission::query()->create($attributes);
        } catch (QueryException $exception) {
            $duplicateSubmissionExists = PatientIntakeSubmission::query()
                ->where('response_id', $responseId)
                ->exists();

            if (! $duplicateSubmissionExists) {
                throw $exception;
            }

            return response()->json(['status' => 'duplicate', 'response_id' => $responseId]);
        }

        return response()->json(['status' => 'accepted', 'response_id' => $responseId], 202);
    }
}
