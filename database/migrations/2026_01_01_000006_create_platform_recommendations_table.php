<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_recommendations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('xishie_projects')->cascadeOnDelete();
            $table->foreignId('academic_venue_id')->nullable()->constrained('academic_venues')->nullOnDelete();
            $table->string('journal_name');
            $table->string('publisher')->nullable();
            $table->string('index_tier', 32)->nullable();
            $table->decimal('match_score', 5, 2)->default(0);
            $table->text('scope_reasoning')->nullable();
            $table->decimal('apc_estimate', 12, 2)->nullable();
            $table->string('submission_url')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'match_score']);
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_recommendations');
    }
};
