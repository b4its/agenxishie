<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Workspace\Enums\UxKpiMetric;
use App\Models\UxKpiTelemetry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UxKpiTelemetry>
 */
class UxKpiTelemetryFactory extends Factory
{
    protected $model = UxKpiTelemetry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $metric = UxKpiMetric::GUIDELINE_COMPLIANCE_SCORE;

        return [
            'project_id' => null,
            'user_id' => null,
            'metric_key' => $metric,
            'metric_category' => $metric->category(),
            'metric_value' => fake()->randomFloat(4, 0, 100),
            'metadata' => [],
            'recorded_at' => now(),
        ];
    }
}
