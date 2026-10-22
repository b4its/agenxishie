<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_stages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('xishie_projects')->cascadeOnDelete();
            $table->string('stage_name', 16)->index();
            $table->string('status', 24)->default('locked')->index();
            $table->json('outline_schema')->nullable();
            $table->json('sections_content')->nullable();
            $table->longText('compiled_content')->nullable();
            $table->json('hitl_pending_payload')->nullable();
            $table->decimal('compliance_score', 5, 2)->nullable();
            $table->unsignedInteger('word_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'stage_name']);
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_stages');
    }
};
