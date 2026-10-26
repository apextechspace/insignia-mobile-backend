<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('Insignia Residences Management')
            ->brandLogo(fn () => asset('images/logo-trimmed.png'))
            ->darkModeBrandLogo(fn () => asset('images/logo-white.png'))
            ->brandLogoHeight('3.5rem')
            ->favicon(fn () => asset('images/logo-trimmed.png'))
            ->font('Plus Jakarta Sans')
            ->darkMode(true)
            ->sidebarCollapsibleOnDesktop()
            ->colors([
                'primary' => [
                    50  => '252, 245, 248',
                    100 => '246, 230, 238',
                    200 => '236, 204, 221',
                    300 => '221, 168, 198',
                    400 => '197, 116, 163',
                    500 => '168, 71, 129',
                    600 => '138, 44, 99',
                    700 => '111, 29, 76',
                    800 => '90, 20, 59',
                    900 => '74, 14, 46', // #4A0E2E - Rich Insignia Plum
                    950 => '46, 5, 26',
                ],
                'gray' => Color::Slate,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
