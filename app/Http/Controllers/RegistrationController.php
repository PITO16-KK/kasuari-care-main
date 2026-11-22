<?php

namespace App\Http\Controllers;

use App\Models\ParentContact;
use App\Models\Patient;
use App\Models\PublicRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationController extends Controller
{
    public function index(): Response
    {
        $registrations = PublicRegistration::with('patient:id,patient_code,name')
            ->latest('submitted_at')
            ->paginate(15);

        $patients = Patient::query()->where('is_active', true)->get(['id', 'patient_code', 'name', 'primary_whatsapp']);

        $registrations->getCollection()->transform(function (PublicRegistration $reg) use ($patients): PublicRegistration {
            if (! $reg->patient_id) {
                $childName = mb_strtolower(trim($reg->child_name));
                $phone = preg_replace('/\D/', '', $reg->whatsapp ?? '');

                $match = $patients->first(function (Patient $p) use ($childName, $phone): bool {
                    $pName = mb_strtolower(trim($p->name));
                    $pPhone = preg_replace('/\D/', '', $p->primary_whatsapp ?? '');

                    return $pName === $childName || (strlen($phone) >= 8 && $pPhone !== '' && str_contains($pPhone, $phone));
                });

                if ($match) {
                    $reg->setAttribute('matched_patient', [
                        'id' => $match->id,
                        'patient_code' => $match->patient_code,
                        'name' => $match->name,
                    ]);
                }
            }

            return $reg;
        });

        return Inertia::render('Registrations/Index', [
            'registrations' => $registrations,
        ]);
    }

    public function convert(Request $request, PublicRegistration $registration): RedirectResponse
    {
        $data = $request->validate([
            'follow_up_notes' => ['nullable', 'string', 'max:3000'],
            'existing_patient_id' => ['nullable', 'integer', 'exists:patients,id'],
        ]);

        $patient = DB::transaction(function () use ($request, $registration, $data) {
            $registration = PublicRegistration::whereKey($registration->id)->lockForUpdate()->firstOrFail();
            if ($registration->patient_id) {
                return Patient::findOrFail($registration->patient_id);
            }

            if (! empty($data['existing_patient_id'])) {
                $patient = Patient::findOrFail($data['existing_patient_id']);
                $registration->update([
                    'status' => 'converted',
                    'assigned_to' => $request->user()->id,
                    'patient_id' => $patient->id,
                    'follow_up_notes' => $data['follow_up_notes'] ?? null,
                ]);

                DB::table('audit_logs')->insert([
                    'actor_id' => $request->user()->id,
                    'event' => 'registration.linked',
                    'entity_type' => Patient::class,
                    'entity_id' => $patient->id,
                    'after' => json_encode(['patient_code' => $patient->patient_code, 'registration_id' => $registration->id]),
                    'ip_address' => $request->ip(),
                    'created_at' => now(),
                ]);

                return $patient;
            }

            $patient = Patient::create([
                'patient_code' => Patient::nextCode(),
                'name' => $registration->child_name,
                'birth_date' => $registration->birth_date,
                'gender' => $registration->gender,
                'address' => $registration->address,
                'primary_whatsapp' => $registration->whatsapp,
                'main_complaint' => $registration->complaint,
                'created_by' => $request->user()->id,
            ]);

            $parent = ParentContact::create([
                'name' => $registration->parent_name,
                'whatsapp' => $registration->whatsapp,
                'email' => $registration->email,
                'address' => $registration->address,
            ]);
            $patient->parents()->attach($parent->id, [
                'relation' => $registration->parent_relation,
                'is_primary_contact' => true,
            ]);

            $registration->update([
                'status' => 'converted',
                'assigned_to' => $request->user()->id,
                'patient_id' => $patient->id,
                'follow_up_notes' => $data['follow_up_notes'] ?? null,
            ]);

            DB::table('audit_logs')->insert([
                'actor_id' => $request->user()->id,
                'event' => 'registration.converted',
                'entity_type' => Patient::class,
                'entity_id' => $patient->id,
                'after' => json_encode(['patient_code' => $patient->patient_code, 'registration_id' => $registration->id]),
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);

            return $patient;
        });

        return redirect()->route('patients.index')->with('success', "Pendaftaran diproses ke pasien {$patient->patient_code}.");
    }
}