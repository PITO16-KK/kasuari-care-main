<?php

namespace App\Http\Controllers;

use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class TherapistController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Therapists/Index', [
            'therapists' => TherapistProfile::query()
                ->with('user:id,name,email,phone,is_active')
                ->latest()
                ->paginate(12),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'specialty' => ['nullable', 'string', 'max:160'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        DB::transaction(function () use ($data, $request): void {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'is_active' => true,
            ]);
            $user->syncRoles([Role::firstOrCreate(['name' => 'therapist', 'guard_name' => 'web'])]);

            $profile = TherapistProfile::create([
                'user_id' => $user->id,
                'specialty' => $data['specialty'] ?? null,
                'license_number' => $data['license_number'] ?? null,
            ]);

            DB::table('audit_logs')->insert([
                'actor_id' => $request->user()->id,
                'event' => 'therapist.created',
                'entity_type' => TherapistProfile::class,
                'entity_id' => $profile->id,
                'after' => json_encode([
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'specialty' => $profile->specialty,
                ], JSON_THROW_ON_ERROR),
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);
        });

        return redirect()->route('therapists.index')->with('success', 'Akun dan profil terapis berhasil dibuat.');
    }

    public function update(Request $request, TherapistProfile $therapist): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($therapist->user_id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'specialty' => ['nullable', 'string', 'max:160'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($data, $request, $therapist): void {
            $therapist->loadMissing('user');
            $user = $therapist->user;
            $before = [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'specialty' => $therapist->specialty,
                'license_number' => $therapist->license_number,
                'is_active' => (bool) $user->is_active,
            ];

            $user->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'is_active' => $data['is_active'],
            ]);
            $therapist->update([
                'specialty' => $data['specialty'] ?? null,
                'license_number' => $data['license_number'] ?? null,
            ]);

            $after = [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'specialty' => $therapist->specialty,
                'license_number' => $therapist->license_number,
                'is_active' => (bool) $user->is_active,
            ];

            DB::table('audit_logs')->insert([
                'actor_id' => $request->user()->id,
                'event' => 'therapist.updated',
                'entity_type' => TherapistProfile::class,
                'entity_id' => $therapist->id,
                'before' => json_encode($before, JSON_THROW_ON_ERROR),
                'after' => json_encode($after, JSON_THROW_ON_ERROR),
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);
        });

        return redirect()->route('therapists.index')->with('success', 'Data terapis berhasil diperbarui.');
    }
}
