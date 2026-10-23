<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('naskah_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('xishie_projects')->cascadeOnDelete();
            $table->string('title', 191);
            // Sumber dokumen: 'local' (unggahan berkas) atau 'project' (tahap proyek).
            $table->string('source_type', 16)->default('local')->index();
            // Tahap proyek asal bila source_type = project (draft/proposal/final).
            $table->string('source_stage', 16)->nullable();
            $table->string('source_filename', 191)->nullable();
            // Blok naskah terstruktur: [['type' => heading|paragraph|quote|bullet|numbered, 'level' => 1..3, 'text' => '...']]
            $table->json('blocks');
            $table->unsignedInteger('word_count')->default(0);
            $table->timestamp('last_saved_at')->nullable();
            $table->timestamps();

            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('naskah_documents');
    }
};
