<?php

namespace App\Http\Controllers;

use App\Models\PatientIntakeSubmission;
use App\Models\PublicRegistration;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoogleFormRegistrationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $configuredToken = config('services.google_forms.token', env('GOOGLE_FORMS_TOKEN', 'kasuari-care-gform-2026'));
        $receivedToken = $request->header('X-Webhook-Token') ?? $request->query('token') ?? $request->input('token');

        if ($configuredToken && $receivedToken !== $configuredToken) {
            return response()->json(['status' => 'error', 'message' => 'Token webhook tidak valid.'], 401);
        }

        $payload = $request->all();
        $answers = $payload['answers'] ?? $payload['responses'] ?? $payload;

        $findAnswer = function (array $patterns) use ($answers): ?string {
            foreach ($answers as $question => $answer) {
                if (! is_string($question) || empty($answer)) {
                    continue;
                }
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $question)) {
                        return is_array($answer) ? implode(', ', $answer) : trim((string) $answer);
                    }
                }
            }

            return null;
        };

        // Extract Child info
        $childName = $findAnswer(['/nama\s*anak/i']) ?? $payload['child_name'] ?? 'Anak (Tanpa Nama)';
        $rawBirthDate = $findAnswer(['/tanggal\s*lahir/i']) ?? $payload['birth_date'] ?? null;
        $birthDate = null;
        if ($rawBirthDate) {
            try {
                $birthDate = Carbon::parse($rawBirthDate)->format('Y-m-d');
            } catch (\Throwable) {
                $birthDate = null;
            }
        }

        $address = $findAnswer(['/alamat/i', '/tempat\s*tinggal/i']) ?? $payload['address'] ?? null;

        // Extract Parent info
        $motherInfo = $findAnswer(['/nama\s*ibu/i']);
        $fatherInfo = $findAnswer(['/nama\s*ayah/i']);

        $parentName = 'Orang Tua / Wali';
        $parentRelation = 'guardian';

        if ($motherInfo) {
            $parts = explode(',', $motherInfo);
            $parentName = trim($parts[0]);
            $parentRelation = 'mother';
        } elseif ($fatherInfo) {
            $parts = explode(',', $fatherInfo);
            $parentName = trim($parts[0]);
            $parentRelation = 'father';
        }

        // Extract WhatsApp
        $whatsapp = null;
        $allText = ($motherInfo ?? '').' '.($fatherInfo ?? '').' '.json_encode($answers);
        if (preg_match('/(?:08|\+628|628)\d{8,13}/', $allText, $phoneMatch)) {
            $whatsapp = preg_replace('/\D/', '', $phoneMatch[0]);
            if (str_starts_with($whatsapp, '628')) {
                $whatsapp = '0'.substr($whatsapp, 2);
            }
        }

        // Extract Complaints & Summary
        $complaintParts = [];
        $diagnosis = $findAnswer(['/diagnosis/i']);
        if ($diagnosis) {
            $complaintParts[] = "Diagnosis: {$diagnosis}";
        }
        $speech = $findAnswer(['/kejelasan\s*bicara/i', '/masalah\s*komunikasi/i']);
        if ($speech) {
            $complaintParts[] = "Bicara/Komunikasi: {$speech}";
        }
        $perceived = $findAnswer(['/penyebab\s*masalah/i']);
        if ($perceived) {
            $complaintParts[] = "Keluhan orang tua: {$perceived}";
        }

        $complaint = $complaintParts !== []
            ? implode(" | ", $complaintParts)
            : 'Pendaftaran mandiri melalui Google Form Rekam Medis Anak';

        $submittedAt = now();
        if (! empty($payload['submitted_at'])) {
            try {
                $submittedAt = Carbon::parse($payload['submitted_at']);
            } catch (\Throwable) {
                $submittedAt = now();
            }
        }

        // 1. Save directly into public_registrations table (Pendaftaran Masuk)
        $registration = PublicRegistration::create([
            'child_name' => $childName,
            'birth_date' => $birthDate,
            'gender' => null,
            'parent_name' => $parentName,
            'parent_relation' => $parentRelation,
            'whatsapp' => $whatsapp ?? '-',
            'email' => $payload['email'] ?? null,
            'address' => $address,
            'complaint' => $complaint,
            'status' => 'new',
            'submitted_at' => $submittedAt,
        ]);

        // 2. Also save into patient_intake_submissions table (Form Intake Rekam Medis Detail)
        $intake = null;
        try {
            $otherAnswers = [];
            foreach ($answers as $q => $a) {
                if (is_string($q)) {
                    $otherAnswers[] = [
                        'question' => $q,
                        'answer' => is_array($a) ? implode(', ', $a) : (string) $a,
                    ];
                }
            }

            $intake = PatientIntakeSubmission::create([
                'source' => 'google_forms',
                'response_id' => $payload['response_id'] ?? ('GFORM-'.now()->timestamp.'-'.rand(100, 999)),
                'submitted_at' => $submittedAt,
                'verification_status' => 'pending',
                'child_data' => [
                    'child_name' => $childName,
                    'birth_date' => $birthDate,
                    'current_address' => $address,
                ],
                'father_data' => [
                    'contact_details' => $fatherInfo,
                    'occupation' => $findAnswer(['/pekerjaan\s*ayah/i']),
                ],
                'mother_data' => [
                    'contact_details' => $motherInfo,
                    'occupation' => $findAnswer(['/pekerjaan\s*ibu/i']),
                ],
                'other_answers' => $otherAnswers,
            ]);
        } catch (\Throwable) {
            // Non-blocking fallback
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data pendaftaran dari Google Form berhasil diterima dan masuk ke sistem Kasuari Care.',
            'registration_id' => $registration->id,
            'intake_id' => $intake?->id,
        ], 201);
    }
}
