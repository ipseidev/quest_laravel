<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Livewire\Livewire;

class AdminPanelProvider extends PanelProvider
{
    /**
     * The session layer the panel needs, kept in one place because two different
     * routes have to be given it: the panel's own pages, and Livewire's update
     * endpoint.
     *
     * In an ordinary Laravel app neither would need spelling out — both would
     * inherit the `web` group. Here `web` has been stripped of its session
     * middleware on purpose so the marketing site sets no cookies at all, which
     * means anything that does need a session has to ask for it explicitly.
     *
     * @var list<class-string>
     */
    private const SESSION_MIDDLEWARE = [
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        ShareErrorsFromSession::class,
        PreventRequestForgery::class,
        SubstituteBindings::class,
    ];

    /**
     * Point Livewire's update endpoint at the same session middleware the panel
     * uses.
     *
     * Livewire registers that route on the `web` group, and every interaction in
     * the panel — including the login form submit — is a POST to it. On the gutted
     * `web` group it runs with no session at all, so `Auth::login()` writes to a
     * store that is never persisted and no cookie comes back. The visible symptom
     * is a login that reports no error and lands you back on the login screen.
     *
     * Safe to set globally: Livewire is in this application only because Filament
     * brought it, and the panel is its only consumer.
     */
    public function boot(): void
    {
        Livewire::setUpdateRoute(
            fn ($handle) => Route::post('/livewire/update', $handle)
                ->middleware(self::SESSION_MIDDLEWARE),
        );
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            /*
             * Not the default `web` guard. See the `admin` entry in config/auth.php:
             * authenticating into `web` would make `BelongsToCurrentUserScope` narrow
             * every dashboard aggregate to the operator's own rows.
             */
            ->authGuard('admin')
            ->brandName('Nacre')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                ...self::SESSION_MIDDLEWARE,
                AuthenticateSession::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
