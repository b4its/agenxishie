<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\AarrrFunnelWidget;
use App\Filament\Widgets\AgentActivityTerminalWidget;
use App\Filament\Widgets\HeartKpiTelemetryWidget;
use App\Filament\Widgets\WorkspaceKpiBarWidget;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * UxAnalyticsDashboard: dashboard agregat 18 UX KPI (HEART + AARRR),
 * telemetri agen, dan terminal aktivitas — PRD 4.2 "UX Analytics Dashboard".
 *
 * Halaman ini menampilkan seluruh widget yang didiscover panel:
 * WorkspaceKpiBarWidget (Task & Quality), HeartKpiTelemetryWidget (HEART),
 * AarrrFunnelWidget (AARRR), dan AgentActivityTerminalWidget (log live).
 */
class UxAnalyticsDashboard extends BaseDashboard
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'UX Analytics';

    protected static ?string $title = 'UX Analytics Dashboard';

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'ux-analytics';

    /**
     * @return array<int, class-string>
     */
    public function getWidgets(): array
    {
        return [
            WorkspaceKpiBarWidget::class,
            HeartKpiTelemetryWidget::class,
            AarrrFunnelWidget::class,
            AgentActivityTerminalWidget::class,
        ];
    }

    /**
     * @return array<string, int|string>
     */
    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'md' => 2,
            'xl' => 3,
        ];
    }
}
