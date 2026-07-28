<?php

namespace App\Http\Controllers;

use App\Services\Billing\RevenueCatEntitlements;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives RevenueCat's server-to-server webhook — the only channel by which this
 * server learns that someone holds Nacre Plus.
 *
 * Unauthenticated in the Sanctum sense (RevenueCat carries no user token) and
 * authorised instead by a shared secret pasted into the RevenueCat dashboard's
 * "Authorization header value" field.
 *
 * Deliberately answers 200 for events it cannot act on — an unknown subscriber, another
 * entitlement, a type with no entitlement meaning. RevenueCat retries any non-2xx with
 * backoff for hours, so returning an error for the normal case of "bought before signing
 * in" would generate a retry storm and bury the deliveries that do matter. What went
 * unapplied is in the response body and in the log.
 */
class RevenueCatWebhookController extends Controller
{
    public function __invoke(Request $request, RevenueCatEntitlements $entitlements): JsonResponse
    {
        if (! $this->authorized($request)) {
            return response()->json([
                'error' => 'unauthenticated',
                'message' => 'Invalid webhook credentials.',
            ], 401);
        }

        $event = $request->input('event');

        if (! is_array($event) || ! isset($event['type'])) {
            return response()->json([
                'error' => 'bad_request',
                'message' => 'Missing event payload.',
            ], 400);
        }

        return response()->json(['status' => $entitlements->apply($event)]);
    }

    /**
     * Compare the request's Authorization header against the configured secret.
     *
     * With no secret configured the endpoint is closed, not open: an unprotected
     * billing webhook would let anyone grant themselves a paid entitlement by posting
     * one JSON body. Logged at error because the symptom otherwise — subscribers
     * silently never being upgraded — looks like a client bug.
     */
    private function authorized(Request $request): bool
    {
        $expected = (string) config('services.revenuecat.webhook_secret');

        if ($expected === '') {
            Log::error('quest.billing.webhook_unconfigured', [
                'message' => 'REVENUECAT_WEBHOOK_SECRET is unset; every webhook is being rejected.',
            ]);

            return false;
        }

        $provided = (string) $request->header('Authorization', '');

        // RevenueCat sends the dashboard value verbatim, so accept it with or without
        // the conventional "Bearer " prefix rather than depending on how it was pasted.
        // hash_equals both ways: a timing-safe comparison is cheap here.
        return hash_equals($expected, $provided)
            || hash_equals('Bearer '.$expected, $provided);
    }
}
