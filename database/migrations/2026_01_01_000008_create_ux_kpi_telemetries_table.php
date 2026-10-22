<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ux_kpi_telemetries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained('xishie_projects')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('metric_key', 64)->index();
            $table->string('metric_category', 32)->index();
            $table->decimal('metric_value', 10, 4)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('recorded_at')->useCurrent()->index();
            $table->timestamps();

            $table->index(['project_id', 'metric_key']);
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ux_kpi_telemetries');
    }
};
