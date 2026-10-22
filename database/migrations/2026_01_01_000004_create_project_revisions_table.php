<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('xishie_projects')->cascadeOnDelete();
            $table->foreignId('stage_id')->nullable()->constrained('project_stages')->nullOnDelete();
            $table->string('reviewer_role', 48)->default('pembimbing_1');
            $table->longText('raw_feedback');
            $table->json('action_items')->nullable();
            $table->json('diff_payload')->nullable();
            $table->longText('response_matrix_doc')->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->timestamps();

            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_revisions');
    }
};
