<?php

namespace App\Http\Controllers;

use App\Models\PublicRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicRegistrationController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Public/Registration');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'child_name' => ['required', 'string', 'max:160'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', 'in:male,female'],
            'parent_name' => ['required', 'string', 'max:160'],
            'parent_relation' => ['required', 'in:father,mother,guardian'],
            'whatsapp' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:180'],
            'address' => ['nullable', 'string', 'max:2000'],
            'complaint' => ['required', 'string', 'max:3000'],
        ]);

        PublicRegistration::create($data);

        return redirect()->route('registration.public')->with('success', 'Pendaftaran berhasil dikirim. Tim kami akan menghubungi Anda untuk langkah selanjutnya.');
    }
}
