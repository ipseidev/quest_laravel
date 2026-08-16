<?php

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Every number the admin panel shows.
 *
 * Two decisions run through the whole class.
 *
 * **It never touches Eloquent.** `Entry`, `Quest`, `Character`, `Quote`, `Chapter`
 * and the three binary models all carry a global scope that narrows queries to
 * `Auth::id()` whenever *anyone* is authenticated. A dashboard built on those
 * models would return numbers that are plausible, silently wrong, and impossible
 * to notice — the panel would report the operator's own journal as if it were the
 * whole product. Query-builder statements cannot have a global scope applied to
 * them, so building here on `DB::table()` makes that failure unrepresentable
 * rather than merely avoided.
 *
 * **Activity comes from `personal_access_tokens.last_used_at`, not content.**
 * Sync writes `entries.updated_at` verbatim from the client, so it reflects the
 * device's clock and says nothing reliable about when the server last heard from
 * anyone. Sanctum stamps `last_used_at` server-side on every authenticated
 * request, which makes it the one honest activity signal that also has history
 * predating this dashboard. It measures "the app synced", which for an
 * offline-first journal is close to but not the same as "the user wrote".
 */
class Metrics
{
    /**
     * Accounts, activity and revenue as of now, each with the equivalent figure one
     * period earlier so the panel can show a direction rather than a bare number.
     *
     * @return array<string, mixed>
     */
    public function overview(int $days = 30): array
    {
        $now = Carbon::now();
        $since = $now->copy()->subDays($days);
        $previous = $now->copy()->subDays($days * 2);

        $revenue = $this->revenue();

        return [
            'total_accounts' => DB::table('users')->count(),
            'signups' => DB::table('users')->where('created_at', '>=', $since)->count(),
            'signups_previous' => DB::table('users')
                ->whereBetween('created_at', [$previous, $since])
                ->count(),
            'active' => $this->activeAccounts($since),
            'active_previous' => $this->activeAccounts($previous, $since),
            'subscribers' => $revenue['subscribers'],
            'mrr' => $revenue['mrr'],
            'arr' => $revenue['mrr'] * 12,
            'unmapped_products' => $revenue['unmapped'],
            'activation_rate' => $this->activationRate(),
        ];
    }

    /**
     * Accounts the server heard from in a window. Counts accounts, not tokens: a
     * phone and an iPad signed into the same account are one active person.
     */
    public function activeAccounts(Carbon $since, ?Carbon $until = null): int
    {
        $query = DB::table('personal_access_tokens')
            ->where('tokenable_type', 'App\Models\User')
            ->where('last_used_at', '>=', $since);

        if ($until !== null) {
            $query->where('last_used_at', '<', $until);
        }

        return $query->distinct()->count('tokenable_id');
    }

    /**
     * Share of all accounts that ever wrote a page. The single most useful product
     * number here: an install that never writes is a marketing cost that produced
     * nothing, and it separates an acquisition problem from an onboarding one.
     */
    public function activationRate(): float
    {
        $total = DB::table('users')->count();

        if ($total === 0) {
            return 0.0;
        }

        $activated = DB::table('users')
            ->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('entries')
                ->whereColumn('entries.user_id', 'users.id')
                ->where('entries.is_deleted', false))
            ->count();

        return round($activated / $total * 100, 1);
    }

    /**
     * Daily signups split by the platform of the account's devices.
     *
     * The `unknown` bucket is not noise to be hidden: every account created before
     * device capture shipped lands there, as does anyone still running a build that
     * does not report it. Folding those into iOS — the safe-looking guess, since
     * iOS shipped first — would invent an iOS lead that the data does not support.
     *
     * @return Collection<int, array{date: string, ios: int, android: int, unknown: int}>
     */
    public function signupsByDay(int $days = 30): Collection
    {
        $rows = DB::table('users')
            ->leftJoinSub($this->userPlatforms(), 'p', 'p.user_id', '=', 'users.id')
            ->where('users.created_at', '>=', Carbon::now()->subDays($days)->startOfDay())
            ->selectRaw("date_trunc('day', users.created_at) as day")
            ->selectRaw("count(*) filter (where p.platform = 'ios') as ios")
            ->selectRaw("count(*) filter (where p.platform = 'android') as android")
            ->selectRaw('count(*) filter (where p.platform is null) as unknown')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->day)->toDateString());

        return $this->fillDays($days, function (string $date) use ($rows): array {
            $row = $rows->get($date);

            return [
                'date' => $date,
                'ios' => (int) ($row->ios ?? 0),
                'android' => (int) ($row->android ?? 0),
                'unknown' => (int) ($row->unknown ?? 0),
            ];
        });
    }

    /**
     * Accounts the server heard from on each day of the window.
     *
     * @return Collection<int, array{date: string, active: int}>
     */
    public function activeByDay(int $days = 30): Collection
    {
        $rows = DB::table('personal_access_tokens')
            ->where('tokenable_type', 'App\Models\User')
            ->where('last_used_at', '>=', Carbon::now()->subDays($days)->startOfDay())
            ->selectRaw("date_trunc('day', last_used_at) as day")
            ->selectRaw('count(distinct tokenable_id) as active')
            ->groupBy('day')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->day)->toDateString());

        return $this->fillDays($days, fn (string $date): array => [
            'date' => $date,
            'active' => (int) ($rows->get($date)->active ?? 0),
        ]);
    }

    /**
     * Where new accounts stop. Each step is a subset of the one above it, so the
     * drop between two rows is the number of people the product lost there.
     *
     * @return array<int, array{step: string, users: int, share: float}>
     */
    public function activationFunnel(int $days = 30): array
    {
        $since = Carbon::now()->subDays($days);
        $base = fn () => DB::table('users')->where('created_at', '>=', $since);

        $signed = $base()->count();

        $wroteOne = $base()
            ->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('entries')
                ->whereColumn('entries.user_id', 'users.id')
                ->where('entries.is_deleted', false))
            ->count();

        $wroteThree = $base()
            ->whereRaw('(select count(*) from entries where entries.user_id = users.id and entries.is_deleted = false) >= 3')
            ->count();

        // The product's actual thesis: a page attached to a quest or a person is
        // what makes rereading by thread possible. Someone who never links has a
        // plain diary and no reason to prefer Nacre.
        // Grouped so the OR binds to the two link checks only. Left ungrouped, SQL
        // precedence turns this into "(new AND linked a quest) OR linked a person"
        // and the step silently counts accounts from outside the window.
        $linked = $base()
            ->where(function ($q) {
                $q->whereExists(fn ($sub) => $sub->select(DB::raw(1))
                    ->from('entry_quests')
                    ->join('entries', 'entries.id', '=', 'entry_quests.entry_id')
                    ->whereColumn('entries.user_id', 'users.id'))
                    ->orWhereExists(fn ($sub) => $sub->select(DB::raw(1))
                        ->from('entry_characters')
                        ->join('entries', 'entries.id', '=', 'entry_characters.entry_id')
                        ->whereColumn('entries.user_id', 'users.id'));
            })
            ->count();

        $steps = [
            ['step' => 'Signed up', 'users' => $signed],
            ['step' => 'Wrote a page', 'users' => $wroteOne],
            ['step' => 'Wrote 3 pages', 'users' => $wroteThree],
            ['step' => 'Linked a quest or person', 'users' => $linked],
        ];

        return array_map(fn (array $step): array => $step + [
            'share' => $signed > 0 ? round($step['users'] / $signed * 100, 1) : 0.0,
        ], $steps);
    }

    /**
     * Weekly signup cohorts against the share still syncing in later weeks.
     *
     * Read down a column, not across a row: week-over-week retention of a single
     * cohort is the product question, while comparing two cohorts at different ages
     * mostly measures how long each has had to churn.
     *
     * @return array<int, array{cohort: string, size: int, weeks: array<int, ?float>}>
     */
    public function retentionCohorts(int $weeks = 8): array
    {
        $start = Carbon::now()->startOfWeek()->subWeeks($weeks - 1);

        $rows = DB::table('users')
            ->leftJoin('personal_access_tokens as t', function ($join) {
                $join->on('t.tokenable_id', '=', 'users.id')
                    ->where('t.tokenable_type', '=', 'App\Models\User');
            })
            ->where('users.created_at', '>=', $start)
            ->selectRaw("date_trunc('week', users.created_at) as cohort")
            ->selectRaw('users.id as user_id')
            ->selectRaw('max(t.last_used_at) as last_seen')
            ->groupBy('cohort', 'users.id')
            ->get();

        $cohorts = [];

        foreach ($rows as $row) {
            $cohort = Carbon::parse($row->cohort);
            $key = $cohort->toDateString();
            $cohorts[$key] ??= ['cohort' => $key, 'size' => 0, 'seen' => []];
            $cohorts[$key]['size']++;

            if ($row->last_seen === null) {
                continue;
            }

            // How many whole weeks after signing up the server last heard from this
            // account. Everything up to that week counts as retained.
            $age = (int) floor($cohort->diffInWeeks(Carbon::parse($row->last_seen)));

            for ($w = 0; $w <= $age; $w++) {
                $cohorts[$key]['seen'][$w] = ($cohorts[$key]['seen'][$w] ?? 0) + 1;
            }
        }

        ksort($cohorts);

        return array_values(array_map(function (array $cohort) use ($weeks): array {
            $age = (int) floor(Carbon::parse($cohort['cohort'])->diffInWeeks(Carbon::now()));
            $series = [];

            for ($w = 0; $w < $weeks; $w++) {
                // Weeks the cohort has not lived through yet are null, not zero — a
                // gap in the grid rather than a cliff that reads as total churn.
                $series[$w] = $w > $age
                    ? null
                    : round(($cohort['seen'][$w] ?? 0) / max($cohort['size'], 1) * 100, 1);
            }

            return ['cohort' => $cohort['cohort'], 'size' => $cohort['size'], 'weeks' => $series];
        }, $cohorts));
    }

    /**
     * Live subscriptions turned into revenue, plus the product ids that could not be
     * priced. See `site.plus.products` for why unmapped ids are surfaced instead of
     * guessed at.
     *
     * @return array{subscribers: int, mrr: float, by_plan: array<string, int>, unmapped: array<string, int>}
     */
    public function revenue(): array
    {
        $map = (array) config('site.plus.products', []);
        $monthly = (float) config('site.plus.monthly');
        $annual = (float) config('site.plus.annual');

        $rows = $this->entitled(DB::table('users'))
            ->selectRaw('subscription_product_id as product, count(*) as total')
            ->groupBy('subscription_product_id')
            ->get();

        $mrr = 0.0;
        $byPlan = ['monthly' => 0, 'annual' => 0];
        $unmapped = [];
        $subscribers = 0;

        foreach ($rows as $row) {
            $count = (int) $row->total;
            $subscribers += $count;
            $plan = $map[$row->product] ?? $this->guessPlan($row->product);

            if ($plan === null) {
                $unmapped[$row->product] = $count;

                continue;
            }

            $byPlan[$plan] += $count;
            // Yearly plans are spread across the twelve months they cover, so the
            // figure is comparable month to month instead of spiking on renewal.
            $mrr += $plan === 'annual' ? $count * ($annual / 12) : $count * $monthly;
        }

        return [
            'subscribers' => $subscribers,
            'mrr' => round($mrr, 2),
            'by_plan' => $byPlan,
            'unmapped' => $unmapped,
        ];
    }

    /**
     * Subscriptions by how close they are to lapsing, so a churn spike is visible
     * before it lands rather than a month after.
     *
     * @return array<string, int>
     */
    public function subscriptionHealth(): array
    {
        $now = Carbon::now();

        return [
            'active' => $this->entitled(DB::table('users'))->count(),
            'expiring_30d' => DB::table('users')
                ->whereNotNull('subscription_product_id')
                ->whereBetween('subscription_expires_at', [$now, $now->copy()->addDays(30)])
                ->count(),
            'lapsed' => DB::table('users')
                ->whereNotNull('subscription_product_id')
                ->whereNotNull('subscription_expires_at')
                ->where('subscription_expires_at', '<=', $now)
                ->count(),
            'ai_opt_in' => DB::table('users')->where('ai_chapters_opt_in', true)->count(),
        ];
    }

    /**
     * Share of accounts that have ever used each feature. Answers which parts of the
     * app the store listing should actually be selling.
     *
     * @return array<int, array{feature: string, users: int, share: float}>
     */
    public function featureAdoption(): array
    {
        $total = max(DB::table('users')->count(), 1);

        $direct = [
            'Pages' => 'entries',
            'Quests' => 'quests',
            'People' => 'characters',
            'Quotes' => 'quotes',
            'Chapters' => 'chapters',
        ];

        $features = [];

        foreach ($direct as $label => $table) {
            $features[$label] = DB::table($table)->distinct()->count('user_id');
        }

        // Binaries hang off entries rather than users, so they need the hop.
        foreach (['Photos' => 'entry_attachments', 'Voice notes' => 'entry_audio', 'Videos' => 'entry_videos'] as $label => $table) {
            $features[$label] = DB::table($table)
                ->join('entries', 'entries.id', '=', $table.'.entry_id')
                ->distinct()
                ->count('entries.user_id');
        }

        $features['AI chapters'] = DB::table('users')->where('ai_chapters_opt_in', true)->count();

        arsort($features);

        return array_values(array_map(fn (string $feature) => [
            'feature' => $feature,
            'users' => $features[$feature],
            'share' => round($features[$feature] / $total * 100, 1),
        ], array_keys($features)));
    }

    /**
     * Accounts by platform, including the accounts that have not reported one.
     *
     * @return array<string, int>
     */
    public function platformSplit(): array
    {
        $rows = DB::table('users')
            ->leftJoinSub($this->userPlatforms(), 'p', 'p.user_id', '=', 'users.id')
            ->selectRaw("coalesce(p.platform, 'unknown') as platform, count(*) as total")
            ->groupBy('platform')
            ->pluck('total', 'platform');

        return [
            'ios' => (int) ($rows['ios'] ?? 0),
            'android' => (int) ($rows['android'] ?? 0),
            'unknown' => (int) ($rows['unknown'] ?? 0),
        ];
    }

    /**
     * Installed app versions among devices seen in the last 30 days.
     *
     * The reason this is worth a widget: builds before 1.0.3 predate EAS Update, so
     * anyone still on one cannot be fixed without an App Review round trip. This is
     * how you find out how many people that is.
     *
     * @return Collection<int, object>
     */
    public function appVersions(): Collection
    {
        return DB::table('devices')
            ->where('last_seen_at', '>=', Carbon::now()->subDays(30))
            ->whereNotNull('app_version')
            ->selectRaw('app_version, platform, count(distinct user_id) as users')
            ->groupBy('app_version', 'platform')
            ->orderByDesc('users')
            ->get();
    }

    /**
     * @return array<string, int>
     */
    public function localeSplit(): array
    {
        return DB::table('users')
            ->selectRaw("coalesce(locale, 'unset') as locale, count(*) as total")
            ->groupBy('locale')
            ->pluck('total', 'locale')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /**
     * How people sign in. Worth watching because Apple and Google accounts arrive
     * without a usable email in some cases, which caps what lifecycle email can
     * ever reach.
     *
     * @return array<string, int>
     */
    public function authMethodSplit(): array
    {
        return [
            'apple' => DB::table('users')->whereNotNull('apple_id')->count(),
            'google' => DB::table('users')->whereNotNull('google_id')->count(),
            'password' => DB::table('users')->whereNotNull('password')->count(),
            'reachable_by_email' => DB::table('users')->whereNotNull('email')->count(),
        ];
    }

    /**
     * Mood is the one piece of page content this panel can read: it is a fixed
     * 50-character label rather than free text, and is the only column on `entries`
     * that is not encrypted. Titles and bodies are neither readable here nor
     * intended to be.
     *
     * @return array<string, int>
     */
    public function moodDistribution(): array
    {
        return DB::table('entries')
            ->whereNotNull('mood')
            ->where('is_deleted', false)
            ->selectRaw('mood, count(*) as total')
            ->groupBy('mood')
            ->orderByDesc('total')
            ->pluck('total', 'mood')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /**
     * Stored bytes, and how many free accounts are near the cap that would prompt
     * them to consider Plus.
     *
     * @return array{total_bytes: int, free_near_quota: int}
     */
    public function storage(): array
    {
        $union = DB::table('entry_attachments')->select('entry_id', 'size_bytes')
            ->unionAll(DB::table('entry_audio')->select('entry_id', 'size_bytes'))
            ->unionAll(DB::table('entry_videos')->select('entry_id', 'size_bytes'));

        $perUser = DB::query()
            ->fromSub($union, 'b')
            ->join('entries', 'entries.id', '=', 'b.entry_id')
            ->selectRaw('entries.user_id, sum(b.size_bytes) as bytes')
            ->groupBy('entries.user_id');

        $quotaBytes = (int) config('site.plus.free_media_quota_mb') * 1024 * 1024;

        $rows = DB::query()->fromSub($perUser, 'u')
            ->leftJoin('users', 'users.id', '=', 'u.user_id')
            ->selectRaw('sum(u.bytes) as total')
            // "Free" is the exact negation of an active entitlement: no product at
            // all, or a product whose expiry has passed. A null expiry *with* a
            // product is a lifetime subscriber and must not be counted here.
            ->selectRaw(
                'count(*) filter (where u.bytes >= ? and (users.subscription_product_id is null
                    or (users.subscription_expires_at is not null and users.subscription_expires_at <= ?))) as near',
                [$quotaBytes * 0.8, Carbon::now()],
            )
            ->first();

        return [
            'total_bytes' => (int) ($rows->total ?? 0),
            'free_near_quota' => (int) ($rows->near ?? 0),
        ];
    }

    /**
     * Narrows to accounts holding Plus right now, matching
     * {@see User::hasActiveSubscription()} exactly.
     *
     * The subtlety worth preserving: a null `subscription_expires_at` alongside a
     * product id is a non-expiring entitlement, not a missing date — RevenueCat's
     * `NON_RENEWING_PURCHASE` produces one. Writing this as
     * `expires_at > now()` silently drops every lifetime subscriber from revenue
     * and from the subscriber count, which is exactly the kind of undercount
     * nobody goes looking for.
     */
    private function entitled(Builder $query): Builder
    {
        return $query
            ->whereNotNull('subscription_product_id')
            ->where(fn (Builder $inner) => $inner
                ->whereNull('subscription_expires_at')
                ->orWhere('subscription_expires_at', '>', Carbon::now()));
    }

    /**
     * One platform per account: the most recently seen device that reported one.
     * Accounts with no reporting device are absent, and callers left-join so they
     * surface as `unknown` rather than disappearing from totals.
     */
    private function userPlatforms(): Builder
    {
        return DB::table('devices')
            ->whereNotNull('platform')
            ->selectRaw('distinct on (user_id) user_id, platform')
            ->orderByRaw('user_id, last_seen_at desc nulls last');
    }

    /**
     * Last resort when `site.plus.products` has no entry for an id. Only recognises
     * shapes RevenueCat ids conventionally use; anything else returns null and is
     * reported as unmapped rather than priced wrongly.
     */
    private function guessPlan(?string $productId): ?string
    {
        if ($productId === null) {
            return null;
        }

        $id = strtolower($productId);

        return match (true) {
            str_contains($id, 'annual'), str_contains($id, 'year'), str_contains($id, '1y') => 'annual',
            str_contains($id, 'month'), str_contains($id, '1m') => 'monthly',
            default => null,
        };
    }

    /**
     * Turns a sparse grouped result into a dense day series, so a day with no rows
     * plots as zero instead of shortening the axis.
     *
     * @param  callable(string): array<string, mixed>  $make
     * @return Collection<int, array<string, mixed>>
     */
    private function fillDays(int $days, callable $make): Collection
    {
        $start = Carbon::now()->subDays($days)->startOfDay();

        return collect(range(0, $days))
            ->map(fn (int $offset) => $make($start->copy()->addDays($offset)->toDateString()));
    }
}
