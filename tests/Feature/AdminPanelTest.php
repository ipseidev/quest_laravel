<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\ActivationFunnel;
use App\Filament\Widgets\ActivityChart;
use App\Filament\Widgets\AudienceBreakdown;
use App\Filament\Widgets\FeatureAdoption;
use App\Filament\Widgets\OverviewStats;
use App\Filament\Widgets\RetentionCohorts;
use App\Filament\Widgets\SignupsChart;
use App\Models\Admin;
use App\Models\Entry;
use App\Models\User;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The panel exists on an application whose whole web layer was built to be
 * session-free and cookie-free. These tests pin the two things that arrangement
 * could quietly break: that journal accounts cannot reach the panel, and that the
 * marketing site did not gain cookies when the panel gained sessions.
 */
class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_is_closed_to_anonymous_visitors(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_an_admin_reaches_the_dashboard(): void
    {
        $this->signIn(Admin::factory()->create())
            ->get('/admin')
            ->assertOk();
    }

    /**
     * `Tests\TestCase::call()` forgets resolved guards before every request so that
     * token auth is re-resolved per call, which discards the in-memory user that
     * `actingAs()` sets. The panel authenticates from the session instead, so the
     * session is what a test has to seed.
     */
    private function signIn(Admin $admin): self
    {
        $guard = auth()->guard('admin');

        return $this->withSession([$guard->getName() => $admin->getAuthIdentifier()]);
    }

    /**
     * The reason `admins` is a separate table rather than a flag on `users`. A
     * journal account is authenticated by the mobile API all day long; none of that
     * may ever amount to panel access.
     */
    public function test_an_app_account_cannot_reach_the_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin')
            ->assertRedirect('/admin/login');
    }

    public function test_the_admin_guard_resolves_against_a_different_table(): void
    {
        $provider = auth()->guard('admin')->getProvider();

        $this->assertInstanceOf(Admin::class, $provider->createModel());
        $this->assertNotSame('users', $provider->createModel()->getTable());
    }

    /**
     * Sessions came back for the panel's own middleware stack only. If they ever
     * leak back into the global `web` group, the public site starts setting a
     * cookie — which on a French informational site is the difference between
     * needing a consent banner and not.
     *
     * `SiteTest::test_no_page_sets_a_cookie` covers the marketing pages themselves;
     * this asserts the mechanism those pages depend on.
     */
    public function test_the_web_group_still_has_no_session_middleware(): void
    {
        $middleware = app(Kernel::class)
            ->getMiddlewareGroups()['web'];

        $this->assertNotContains(StartSession::class, $middleware);
        $this->assertNotContains(EncryptCookies::class, $middleware);
        $this->assertNotContains(PreventRequestForgery::class, $middleware);
    }

    /**
     * A 200 on the dashboard does not prove the widgets work: they are Livewire
     * components rendered after the page. Each is mounted directly so a broken
     * query surfaces here rather than as an empty panel.
     *
     * Seeded with one account holding every shape of data the widgets touch, so
     * they render a populated state rather than the trivially safe empty one.
     */
    public function test_every_dashboard_widget_renders(): void
    {
        $user = User::factory()->create([
            'subscription_product_id' => 'nacre_plus_monthly',
            'subscription_expires_at' => now()->addMonth(),
        ]);
        $user->createToken('mobile');
        $user->tokens()->update(['last_used_at' => now()]);
        $user->devices()->create([
            'device_id' => (string) Str::uuid(),
            'platform' => 'android',
            'app_version' => '1.0.4',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
        Entry::factory()->count(3)->for($user)->create();

        $this->signIn(Admin::factory()->create());

        foreach ([
            OverviewStats::class,
            SignupsChart::class,
            ActivityChart::class,
            ActivationFunnel::class,
            FeatureAdoption::class,
            RetentionCohorts::class,
            AudienceBreakdown::class,
        ] as $widget) {
            Livewire::test($widget)->assertOk();
        }
    }

    public function test_the_support_list_and_single_account_view_render(): void
    {
        $user = User::factory()->create();
        Entry::factory()->for($user)->create();

        $this->signIn(Admin::factory()->create());

        $this->get('/admin/users')->assertOk();
        $this->get('/admin/users/'.$user->id)->assertOk();
    }

    /**
     * The panel is a support tool, not an editor. Accounts belong to the people who
     * created them.
     */
    public function test_the_panel_offers_no_way_to_create_edit_or_delete_an_account(): void
    {
        $user = User::factory()->create();

        $this->assertFalse(UserResource::canCreate());
        $this->assertFalse(UserResource::canEdit($user));
        $this->assertFalse(UserResource::canDelete($user));

        $this->signIn(Admin::factory()->create());
        $this->get('/admin/users/'.$user->id.'/edit')->assertNotFound();
    }

    /**
     * Every interaction in the panel — including the login form submit — is a POST
     * to Livewire's update endpoint, which Livewire registers on the `web` group.
     * On this application that group has no session middleware, so the login ran
     * without a session: `Auth::login()` wrote to a store that was never persisted,
     * no cookie came back, and the browser returned to the login screen reporting
     * no error at all. `AdminPanelProvider::boot()` re-points the route.
     */
    public function test_the_livewire_endpoint_has_a_session(): void
    {
        $middleware = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($route): bool => $route->getName() === 'livewire.update')
            ?->gatherMiddleware() ?? [];

        $this->assertContains(StartSession::class, $middleware);
        $this->assertContains(EncryptCookies::class, $middleware);
    }

    /**
     * The suite runs on `SESSION_DRIVER=array`, which never writes a row and is why
     * this went unnoticed: `sessions.user_id` is a uuid column, sized for this
     * schema's uuid accounts, and the database driver writes `Auth::id()` into it.
     * An operator's bigint id made every authenticated page 500 immediately after a
     * successful login.
     *
     * Forced onto the database driver so the write actually happens.
     */
    public function test_an_admin_session_is_writable_by_the_database_driver(): void
    {
        config(['session.driver' => 'database']);

        $admin = Admin::factory()->create();

        $this->signIn($admin)->get('/admin')->assertOk();

        $this->assertDatabaseHas('sessions', ['user_id' => (string) $admin->getKey()]);
    }

    public function test_the_marketing_home_page_still_sets_no_cookie(): void
    {
        $response = $this->get('/');

        $this->assertSame(
            [],
            $response->headers->getCookies(),
            'The marketing site set a cookie after the admin panel introduced sessions.',
        );
    }
}
