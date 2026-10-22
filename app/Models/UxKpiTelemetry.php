<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Workspace\Enums\UxKpiCategory;
use App\Domain\Workspace\Enums\UxKpiMetric;
use Database\Factories\UxKpiTelemetryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UxKpiTelemetry extends Model
{
    /** @use HasFactory<UxKpiTelemetryFactory> */
    use HasFactory;

    protected $table = 'ux_kpi_telemetries';

    protected $fillable = [
        'project_id',
        'user_id',
        'metric_key',
        'metric_category',
        'metric_value',
        'metadata',
        'recorded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metric_key' => UxKpiMetric::class,
            'metric_category' => UxKpiCategory::class,
            'metric_value' => 'decimal:4',
            'metadata' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(XishieProject::class, 'project_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
