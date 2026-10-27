<?php

namespace App\Providers\Filament;

use App\Filament\Operations\Widgets\OperationsDashboardWidget;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
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

class OperationsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('operations')
            ->path('operations')
            ->login()
            ->brandName('Insignia Court Operations')
            ->brandLogo(fn () => asset('images/logo-trimmed.png'))
            ->darkModeBrandLogo(fn () => asset('images/logo-white.png'))
            ->brandLogoHeight('3.5rem')
            ->favicon(fn () => asset('images/logo-trimmed.png'))
            ->font('Plus Jakarta Sans')
            ->darkMode(true)
            // Load custom dashboard utilities alongside (not instead of) Filament v3 CSS.
            ->renderHook(
                'panels::head.end',
                fn (): string => \Illuminate\Support\Facades\Blade::render(
                    "@vite('resources/css/filament-dashboard.css')"
                ),
            )
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
            ->navigationGroups([
                NavigationGroup::make('Operations'),
                NavigationGroup::make('Finance')->collapsible(),
            ])
            ->discoverResources(in: app_path('Filament/Operations/Resources'), for: 'App\\Filament\\Operations\\Resources')
            ->discoverPages(in: app_path('Filament/Operations/Pages'), for: 'App\\Filament\\Operations\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Operations/Widgets'), for: 'App\\Filament\\Operations\\Widgets')
            ->widgets([
                OperationsDashboardWidget::class,
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
