<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xishie_projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('field_of_study', 32)->index();
            $table->string('document_type', 32)->index();
            $table->string('target_scope', 32)->index();
            $table->string('target_tier', 32)->nullable();
            $table->string('control_mode', 16)->default('manual')->index();
            $table->string('current_stage', 16)->default('draft')->index();
            $table->string('guideline_type', 16)->default('default');
            $table->string('citation_style', 16)->default('apa7');
            $table->json('guideline_rules')->nullable();
            $table->string('custom_guideline_path')->nullable();
            $table->timestamp('onboarded_at')->nullable();
            $table->timestamps();

            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xishie_projects');
    }
};
