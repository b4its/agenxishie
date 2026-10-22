<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Pages\UxAnalyticsDashboard;
use App\Filament\Widgets\AarrrFunnelWidget;
use App\Filament\Widgets\AgentActivityTerminalWidget;
use App\Filament\Widgets\HeartKpiTelemetryWidget;
use App\Filament\Widgets\WorkspaceKpiBarWidget;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Panel "Agen Xishie" dengan tema Retro Blue Data-Literate.
 *
 * Primary memakai Cobalt Blue #1E40AF sesuai token desain PRD.
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('AGEN XISHIE')
            ->login()
            // Dukungan Light & Dark theme dengan switcher di topbar.
            ->darkMode(true)
            ->themeSwitcher(true)
            ->defaultThemeMode(ThemeMode::Light)
            ->colors([
                'primary' => Color::hex('#1e40af'),
                'info' => Color::hex('#2563eb'),
                'warning' => Color::hex('#d97706'),
                'success' => Color::hex('#059669'),
                'danger' => Color::hex('#dc2626'),
                'gray' => Color::Slate,
            ])
            ->font('Space Grotesk')
            ->viteTheme('resources/css/app.css')
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth('full')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                UxAnalyticsDashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                WorkspaceKpiBarWidget::class,
                HeartKpiTelemetryWidget::class,
                AarrrFunnelWidget::class,
                AgentActivityTerminalWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
