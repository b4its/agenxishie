<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_venues', function (Blueprint $table): void {
            $table->id();
            $table->string('journal_name')->index();
            $table->string('publisher')->nullable();
            $table->string('scope_type', 16)->index();
            $table->string('index_tier', 32)->index();
            $table->string('field_of_study', 32)->nullable()->index();
            $table->text('aims_and_scope');
            $table->decimal('apc_estimate', 12, 2)->nullable();
            $table->string('submission_url')->nullable();
            $table->unsignedSmallInteger('review_time_days')->nullable();
            $table->json('template_rules')->nullable();
            $table->json('aims_scope_embedding')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->fullText(['journal_name', 'aims_and_scope']);
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_venues');
    }
};
