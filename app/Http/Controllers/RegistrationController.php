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
        return Inertia::render('Registrations/Index', [
            'registrations' => PublicRegistration::with('patient:id,patient_code,name')->latest('submitted_at')->paginate(15),
        ]);
    }

    public function convert(Request $request, PublicRegistration $registration): RedirectResponse
    {
        $request->validate(['follow_up_notes' => ['nullable', 'string', 'max:3000']]);

        $patient = DB::transaction(function () use ($request, $registration) {
            $registration = PublicRegistration::whereKey($registration->id)->lockForUpdate()->firstOrFail();
            if ($registration->patient_id) {
                return Patient::findOrFail($registration->patient_id);
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
                'follow_up_notes' => $request->input('follow_up_notes'),
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

        return redirect()->route('patients.index')->with('success', "Pendaftaran dikonversi menjadi pasien {$patient->patient_code}.");
    }
}