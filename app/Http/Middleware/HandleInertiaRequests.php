<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'roles' => $request->user()->getRoleNames(),
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'assessmentPdfUrl' => fn () => $request->session()->get('assessmentPdfUrl'),
            ],
            'clinic' => [
                'name' => config('clinic.name'),
                'tagline' => config('clinic.tagline'),
                'full_tagline' => config('clinic.full_tagline'),
                'address' => config('clinic.address'),
                'phone' => config('clinic.phone'),
                'email' => config('clinic.email'),
            ],
        ];
    }
}
