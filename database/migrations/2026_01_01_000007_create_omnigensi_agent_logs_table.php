<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omnigensi_agent_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained('xishie_projects')->cascadeOnDelete();
            $table->foreignId('stage_id')->nullable()->constrained('project_stages')->nullOnDelete();
            $table->string('agent_name', 64)->index();
            $table->string('action', 128)->nullable();
            $table->string('control_mode', 16)->nullable();
            $table->unsignedInteger('ttft_ms')->nullable();
            $table->unsignedInteger('execution_time_ms')->nullable();
            $table->unsignedInteger('token_usage')->nullable();
            $table->unsignedInteger('hallucination_rejected_count')->default(0);
            $table->string('status', 24)->default('success')->index();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'created_at']);
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('omnigensi_agent_logs');
    }
};
