<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Models\Quest;
use App\Models\User;
use App\Services\Admin\Metrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class MetricsTest extends TestCase
{
    use RefreshDatabase;

    private Metrics $metrics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->metrics = app(Metrics::class);
    }

    /**
     * The failure this whole design exists to prevent.
     *
     * Every content model filters on `Auth::id()` through a global scope. A
     * dashboard built on Eloquent would, the moment anything authenticated a user,
     * silently report that one account's journal as though it were the product.
     * Nothing would error; the numbers would just be wrong.
     */
    public function test_numbers_do_not_change_when_an_app_user_is_authenticated(): void
    {
        $users = User::factory()->count(3)->create();

        foreach ($users as $user) {
            Entry::factory()->count(2)->for($user)->create();
        }

        $before = [
            $this->metrics->activationRate(),
            $this->metrics->featureAdoption(),
            $this->metrics->overview(),
        ];

        $this->actingAs($users->first());

        $this->assertSame($before, [
            $this->metrics->activationRate(),
            $this->metrics->featureAdoption(),
            $this->metrics->overview(),
        ], 'A global scope leaked into the dashboard and narrowed it to one account.');
    }

    public function test_activation_counts_only_accounts_that_wrote_something(): void
    {
        $wrote = User::factory()->create();
        Entry::factory()->for($wrote)->create();

        User::factory()->create();

        $this->assertSame(50.0, $this->metrics->activationRate());
    }

    public function test_a_page_in_the_trash_does_not_count_as_activation(): void
    {
        $user = User::factory()->create();
        Entry::factory()->for($user)->create(['is_deleted' => true]);

        $this->assertSame(0.0, $this->metrics->activationRate());
    }

    /**
     * A non-renewing purchase leaves `subscription_expires_at` null while the
     * entitlement is live. Pricing this as "expired" is an undercount that would
     * never announce itself, so it is pinned here.
     */
    public function test_a_lifetime_entitlement_counts_as_an_active_subscriber(): void
    {
        User::factory()->create([
            'subscription_product_id' => 'nacre_plus_lifetime',
            'subscription_expires_at' => null,
        ]);

        $this->assertSame(1, $this->metrics->revenue()['subscribers']);
        $this->assertSame(1, $this->metrics->subscriptionHealth()['active']);
    }

    public function test_a_lapsed_subscription_is_not_an_active_subscriber(): void
    {
        User::factory()->create([
            'subscription_product_id' => 'nacre_plus_monthly',
            'subscription_expires_at' => Carbon::now()->subDay(),
        ]);

        $this->assertSame(0, $this->metrics->revenue()['subscribers']);
        $this->assertSame(1, $this->metrics->subscriptionHealth()['lapsed']);
    }

    public function test_mrr_spreads_an_annual_plan_across_the_year(): void
    {
        config(['site.plus.products' => ['yearly_id' => 'annual', 'monthly_id' => 'monthly']]);

        User::factory()->create([
            'subscription_product_id' => 'yearly_id',
            'subscription_expires_at' => Carbon::now()->addYear(),
        ]);
        User::factory()->create([
            'subscription_product_id' => 'monthly_id',
            'subscription_expires_at' => Carbon::now()->addMonth(),
        ]);

        $expected = round(config('site.plus.annual') / 12 + config('site.plus.monthly'), 2);

        $this->assertSame($expected, $this->metrics->revenue()['mrr']);
    }

    /**
     * An unrecognised product must be surfaced, not folded into a plan. A wrong MRR
     * that looks plausible is worse than one that says it cannot price something.
     */
    public function test_an_unpriceable_product_is_reported_rather_than_guessed(): void
    {
        config(['site.plus.products' => []]);

        User::factory()->create([
            'subscription_product_id' => 'com.affiniteam.quest.mystery',
            'subscription_expires_at' => Carbon::now()->addMonth(),
        ]);

        $revenue = $this->metrics->revenue();

        $this->assertSame(0.0, $revenue['mrr']);
        $this->assertSame(1, $revenue['subscribers']);
        $this->assertSame(['com.affiniteam.quest.mystery' => 1], $revenue['unmapped']);
    }

    public function test_accounts_with_no_reported_platform_are_their_own_bucket(): void
    {
        $ios = User::factory()->create();
        $ios->devices()->create([
            'device_id' => (string) Str::uuid(),
            'platform' => 'ios',
            'last_seen_at' => Carbon::now(),
        ]);

        User::factory()->count(2)->create();

        $this->assertSame(
            ['ios' => 1, 'android' => 0, 'unknown' => 2],
            $this->metrics->platformSplit(),
        );
    }

    /**
     * The platform of an account is that of its most recent device, so someone who
     * moved from an iPhone to a Pixel counts once, on Android.
     */
    public function test_the_most_recent_device_decides_the_platform(): void
    {
        $user = User::factory()->create();

        $user->devices()->create([
            'device_id' => (string) Str::uuid(),
            'platform' => 'ios',
            'last_seen_at' => Carbon::now()->subMonth(),
        ]);
        $user->devices()->create([
            'device_id' => (string) Str::uuid(),
            'platform' => 'android',
            'last_seen_at' => Carbon::now(),
        ]);

        $this->assertSame(
            ['ios' => 0, 'android' => 1, 'unknown' => 0],
            $this->metrics->platformSplit(),
        );
    }

    public function test_activity_comes_from_tokens_not_from_client_written_timestamps(): void
    {
        $active = User::factory()->create();
        $active->createToken('mobile');
        $active->tokens()->update(['last_used_at' => Carbon::now()->subDay()]);

        $stale = User::factory()->create();
        $stale->createToken('mobile');
        $stale->tokens()->update(['last_used_at' => Carbon::now()->subMonths(3)]);

        // A page whose client-supplied updated_at is today must not make a dormant
        // account look active.
        Entry::factory()->for($stale)->create(['updated_at' => Carbon::now()]);

        $this->assertSame(1, $this->metrics->activeAccounts(Carbon::now()->subDays(30)));
    }

    public function test_the_funnel_narrows_step_by_step(): void
    {
        $linker = User::factory()->create();
        Entry::factory()->count(3)->for($linker)->create();
        $quest = Quest::factory()->for($linker)->create();
        $linker->entries()->first()->quests()->attach($quest->id);

        $writer = User::factory()->create();
        Entry::factory()->for($writer)->create();

        User::factory()->create();

        $steps = collect($this->metrics->activationFunnel())->keyBy('step');

        $this->assertSame(3, $steps['Signed up']['users']);
        $this->assertSame(2, $steps['Wrote a page']['users']);
        $this->assertSame(1, $steps['Wrote 3 pages']['users']);
        $this->assertSame(1, $steps['Linked a quest or person']['users']);
    }

    /**
     * The step counts accounts created inside the window. An older account that
     * links today must not be pulled into a window it does not belong to — the bug
     * SQL precedence produces if the two link checks are not grouped.
     */
    public function test_the_link_step_stays_inside_the_window(): void
    {
        $old = User::factory()->create(['created_at' => Carbon::now()->subYear()]);
        $entry = Entry::factory()->for($old)->create();
        $quest = Quest::factory()->for($old)->create();
        $entry->quests()->attach($quest->id);

        $steps = collect($this->metrics->activationFunnel(30))->keyBy('step');

        $this->assertSame(0, $steps['Signed up']['users']);
        $this->assertSame(0, $steps['Linked a quest or person']['users']);
    }

    public function test_the_day_series_is_dense_even_where_nothing_happened(): void
    {
        User::factory()->create();

        $days = $this->metrics->signupsByDay(14);

        $this->assertCount(15, $days);
        $this->assertSame(1, collect($days)->sum(fn (array $d): int => $d['unknown']));
    }
}
