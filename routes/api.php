<?php

use App\Http\Controllers\GoogleFormIntakeWebhookController;
use App\Http\Middleware\VerifyGoogleFormWebhookSignature;
use Illuminate\Support\Facades\Route;

Route::post('/integrations/google-form/intakes', GoogleFormIntakeWebhookController::class)
    ->middleware(['throttle:30,1', VerifyGoogleFormWebhookSignature::class])
    ->name('integrations.google-form.intakes.store');
