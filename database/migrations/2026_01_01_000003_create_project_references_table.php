<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_references', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained('xishie_projects')->cascadeOnDelete();
            $table->string('citation_key', 191)->index();
            $table->string('doi', 191)->nullable()->index();
            $table->string('title');
            $table->text('abstract')->nullable();
            $table->json('authors')->nullable();
            $table->unsignedSmallInteger('year')->nullable()->index();
            $table->string('journal_name')->nullable();
            $table->string('sinta_or_scopus_tier', 32)->nullable();
            $table->json('matrix_extraction')->nullable();
            $table->json('embedding')->nullable();
            $table->string('source', 32)->nullable();
            $table->string('pdf_path')->nullable();
            $table->boolean('is_verified')->default(false)->index();
            $table->timestamps();

            $table->fullText(['title', 'abstract']);
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_references');
    }
};
