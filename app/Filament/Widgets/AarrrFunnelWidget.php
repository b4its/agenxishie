<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

/**
 * AarrrFunnelWidget: metrik funnel AARRR (Acquisition, Activation, Retention,
 * Conversion, Referral).
 */
class AarrrFunnelWidget extends HeartKpiTelemetryWidget
{
    protected static ?int $sort = 3;

    public function mount(): void
    {
        $this->framework = 'AARRR';
    }
}
