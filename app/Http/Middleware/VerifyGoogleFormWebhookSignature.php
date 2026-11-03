<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyGoogleFormWebhookSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.google_forms.webhook_secret');

        if (! is_string($secret) || strlen($secret) < 32) {
            return response()->json(['message' => 'Webhook authentication is not configured.'], 503);
        }

        if (strlen($request->getContent()) > 262144) {
            return response()->json(['message' => 'Request payload is too large.'], 413);
        }

        $timestamp = $request->header('X-Kasuari-Timestamp');
        $signature = $request->header('X-Kasuari-Signature');

        if (! is_string($timestamp) || preg_match('/^\d{10}$/', $timestamp) !== 1
            || abs(now()->timestamp - (int) $timestamp) > 300
            || ! is_string($signature) || preg_match('/^[a-f0-9]{64}$/i', $signature) !== 1) {
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        $expectedSignature = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        if (! hash_equals($expectedSignature, strtolower($signature))) {
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        return $next($request);
    }
}
