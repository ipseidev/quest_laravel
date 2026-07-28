<?php

namespace App\Services\Billing;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Applies a RevenueCat webhook event to an account's Nacre Plus entitlement.
 *
 * This is the only thing that writes `users.subscription_product_id` /
 * `subscription_expires_at`. Everything paid reads those two columns through
 * `User::hasActiveSubscription()` — the free media quota in `BackupQuotaService`, the
 * scheduled recurring Chapter commands, and `hasAiAccess()` — so until this ran, a
 * paying subscriber was indistinguishable from a free account on the server: capped at
 * the free media quota and receiving no monthly Chapter, while the app (which reads
 * RevenueCat directly) showed them as premium.
 *
 * Design notes that are easy to get wrong:
 *
 * - **Cancelling is not expiring.** A CANCELLATION means auto-renew was turned off; the
 *   subscriber keeps access until the period ends. Only a refund ends it immediately.
 * - **Events arrive out of order.** Retries mean a RENEWAL can land after the
 *   CANCELLATION that followed it, which would re-grant a finished subscription. Any
 *   event older than the last one applied to that account is dropped.
 * - **A purchase can predate the account.** Someone can buy before signing in, under a
 *   RevenueCat anonymous id that matches no user here. That is normal, not an error.
 *   The entitlement reaches the account later via the TRANSFER event that
 *   `Purchases.logIn()` triggers — which requires the RevenueCat dashboard's transfer
 *   behaviour to be "Transfer to new App User ID".
 */
class RevenueCatEntitlements
{
    /** Event types that grant Plus or push its expiry out. */
    private const GRANTS = [
        'INITIAL_PURCHASE',
        'RENEWAL',
        'PRODUCT_CHANGE',
        'UNCANCELLATION',
        'NON_RENEWING_PURCHASE',
        'SUBSCRIPTION_EXTENDED',
    ];

    /**
     * Types that write the expiry RevenueCat reports, which may already be in the
     * past. Kept apart from GRANTS because they must never push an expiry outwards.
     */
    private const SETTLES = ['CANCELLATION', 'EXPIRATION'];

    /**
     * Returns a short outcome describing what was done, for the response body and the
     * log: `granted`, `settled`, `transferred`, `stale`, `unknown_subscriber`,
     * `other_entitlement`, `malformed`, `test`, or `ignored`.
     *
     * @param  array<string, mixed>  $event
     */
    public function apply(array $event): string
    {
        $type = strtoupper((string) ($event['type'] ?? ''));

        // The dashboard's "send test event" button. Its app_user_id is not real, so
        // acknowledge it without touching anything.
        if ($type === 'TEST') {
            return 'test';
        }

        if (! $this->concernsPlus($event)) {
            return 'other_entitlement';
        }

        if ($type === 'TRANSFER') {
            return $this->transfer($event);
        }

        if (in_array($type, self::GRANTS, true)) {
            return $this->grant($event, $type);
        }

        if (in_array($type, self::SETTLES, true)) {
            return $this->settle($event, $type);
        }

        /*
         * Everything else carries no entitlement change:
         *
         * - BILLING_ISSUE — a failed payment opens a grace period. RevenueCat keeps the
         *   entitlement active until the expiry it already sent, so revoking here would
         *   cut off a subscriber whose card simply needs re-authorising.
         * - SUBSCRIPTION_PAUSED — a Google Play pause takes effect at the end of the
         *   paid period, which the stored expiry already reflects.
         * - SUBSCRIBER_ALIAS, INVOICE_ISSUANCE, and the rest — bookkeeping.
         */
        Log::info('quest.billing.event_ignored', ['type' => $type]);

        return 'ignored';
    }

    /**
     * Whether the event is about the entitlement this app sells.
     *
     * An absent list is treated as ours: some payload versions omit the field, and this
     * project configures exactly one entitlement. A list that names others but not ours
     * is skipped rather than assumed.
     *
     * @param  array<string, mixed>  $event
     */
    private function concernsPlus(array $event): bool
    {
        $configured = (string) config('services.revenuecat.entitlement');

        $ids = $event['entitlement_ids']
            ?? (isset($event['entitlement_id']) ? [$event['entitlement_id']] : null);

        return $ids === null || in_array($configured, (array) $ids, true);
    }

    /** @param  array<string, mixed>  $event */
    private function grant(array $event, string $type): string
    {
        $user = $this->resolveUser($event);

        if ($user === null) {
            return $this->unknownSubscriber($event);
        }

        $productId = $event['product_id'] ?? null;

        if (! is_string($productId) || $productId === '') {
            return $this->malformed($event, 'grant without a product_id');
        }

        $expiresAt = $this->expiry($event);

        /*
         * A null expiry means "never expires" to `hasActiveSubscription()`. That is
         * right for a one-off purchase and catastrophic for a subscription — it would
         * hand out Plus permanently. Refuse rather than guess.
         */
        if ($expiresAt === null && $type !== 'NON_RENEWING_PURCHASE') {
            return $this->malformed($event, "{$type} without expiration_at_ms");
        }

        return $this->write($user, $event, $productId, $expiresAt) ? 'granted' : 'stale';
    }

    /** @param  array<string, mixed>  $event */
    private function settle(array $event, string $type): string
    {
        $user = $this->resolveUser($event);

        if ($user === null) {
            return $this->unknownSubscriber($event);
        }

        // Keep the product on record even once it has lapsed: it is what a win-back
        // message and a support conversation both need.
        $productId = is_string($event['product_id'] ?? null)
            ? $event['product_id']
            : $user->subscription_product_id;

        $expiresAt = $this->expiry($event) ?? Carbon::now();

        /*
         * A refund arrives as a CANCELLATION, and its expiration is still the end of the
         * period the customer was refunded for. Access has to stop now, not then.
         */
        if ($type === 'CANCELLATION' && ($event['cancel_reason'] ?? null) === 'CUSTOMER_SUPPORT') {
            $expiresAt = Carbon::now();
        }

        return $this->write($user, $event, $productId, $expiresAt) ? 'settled' : 'stale';
    }

    /**
     * An entitlement moving between app user ids — a restore on a different account, or
     * the anonymous-to-signed-in handover that `Purchases.logIn()` performs.
     *
     * @param  array<string, mixed>  $event
     */
    private function transfer(array $event): string
    {
        foreach ($this->usersFor((array) ($event['transferred_from'] ?? [])) as $loser) {
            $this->write($loser, $event, $loser->subscription_product_id, Carbon::now());
        }

        $gainers = $this->usersFor((array) ($event['transferred_to'] ?? []));

        if ($gainers === []) {
            return 'transferred';
        }

        $productId = $event['product_id'] ?? null;
        $expiresAt = $this->expiry($event);

        if (! is_string($productId) || $productId === '') {
            return $this->malformed($event, 'transfer without a product_id');
        }

        foreach ($gainers as $gainer) {
            $this->write($gainer, $event, $productId, $expiresAt);
        }

        return 'transferred';
    }

    /**
     * Persist the entitlement, unless this event predates the one already applied.
     *
     * @param  array<string, mixed>  $event
     */
    private function write(User $user, array $event, ?string $productId, ?Carbon $expiresAt): bool
    {
        $eventAtMs = $this->eventTimestampMs($event);

        if ($user->subscription_event_at_ms !== null && $eventAtMs < $user->subscription_event_at_ms) {
            Log::info('quest.billing.stale_event', [
                'user_id' => $user->id,
                'type' => $event['type'] ?? null,
                'event_at_ms' => $eventAtMs,
                'applied_at_ms' => $user->subscription_event_at_ms,
            ]);

            return false;
        }

        // Direct assignment, not fill(): these columns are deliberately outside the
        // model's #[Fillable] list so no request payload can ever reach them.
        $user->subscription_product_id = $productId;
        $user->subscription_expires_at = $expiresAt;
        $user->subscription_event_at_ms = $eventAtMs;
        $user->save();

        Log::info('quest.billing.entitlement', [
            'user_id' => $user->id,
            'type' => $event['type'] ?? null,
            'product_id' => $productId,
            'expires_at' => $expiresAt?->toIso8601String(),
            'active' => $user->hasActiveSubscription(),
        ]);

        return true;
    }

    /** @param  array<string, mixed>  $event */
    private function resolveUser(array $event): ?User
    {
        $candidates = array_merge(
            [$event['app_user_id'] ?? null, $event['original_app_user_id'] ?? null],
            (array) ($event['aliases'] ?? []),
        );

        return $this->usersFor($candidates)[0] ?? null;
    }

    /**
     * The accounts named by a list of RevenueCat app user ids.
     *
     * Filtered to well-formed UUIDs first. Anonymous ids look like
     * `$RCAnonymousID:8f3a…` and can never be one of ours, and `users.id` is a Postgres
     * `uuid` column — passing one straight through would fail the query rather than
     * simply match nothing.
     *
     * @param  array<int, mixed>  $ids
     * @return list<User>
     */
    private function usersFor(array $ids): array
    {
        $uuids = array_values(array_unique(array_filter(
            $ids,
            fn (mixed $id): bool => is_string($id) && Str::isUuid($id),
        )));

        if ($uuids === []) {
            return [];
        }

        return User::whereIn('id', $uuids)->get()->all();
    }

    /** @param  array<string, mixed>  $event */
    private function expiry(array $event): ?Carbon
    {
        $ms = $event['expiration_at_ms'] ?? null;

        return is_numeric($ms) ? Carbon::createFromTimestampMs((int) $ms)->utc() : null;
    }

    /** @param  array<string, mixed>  $event */
    private function eventTimestampMs(array $event): int
    {
        $ms = $event['event_timestamp_ms'] ?? null;

        return is_numeric($ms) ? (int) $ms : Carbon::now()->getTimestampMs();
    }

    /** @param  array<string, mixed>  $event */
    private function unknownSubscriber(array $event): string
    {
        // Expected whenever someone bought before creating an account. Logged at info,
        // not error: the entitlement reaches them on the later TRANSFER event.
        Log::info('quest.billing.unknown_subscriber', [
            'type' => $event['type'] ?? null,
            'app_user_id' => $event['app_user_id'] ?? null,
        ]);

        return 'unknown_subscriber';
    }

    /**
     * A payload we refuse to act on. Logged at error because it means either RevenueCat
     * changed its schema or the dashboard is misconfigured — and in both cases a real
     * customer is paying without being served.
     *
     * @param  array<string, mixed>  $event
     */
    private function malformed(array $event, string $reason): string
    {
        Log::error('quest.billing.malformed_event', [
            'reason' => $reason,
            'type' => $event['type'] ?? null,
            'app_user_id' => $event['app_user_id'] ?? null,
        ]);

        return 'malformed';
    }
}
