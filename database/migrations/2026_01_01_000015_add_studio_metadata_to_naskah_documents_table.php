<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('naskah_documents', function (Blueprint $table): void {
            // Komentar dan revisi (track changes) sebagai JSON.
            $table->json('comments')->nullable()->after('blocks');
            $table->json('revisions')->nullable()->after('comments');
            // Pengaturan halaman: ukuran, orientasi, margin, kolom, warna, watermark, dll.
            $table->json('page_setup')->nullable()->after('revisions');
            // Gaya global dokumen (tema, font default, style set).
            $table->json('style_meta')->nullable()->after('page_setup');
        });
    }

    public function down(): void
    {
        Schema::table('naskah_documents', function (Blueprint $table): void {
            $table->dropColumn(['comments', 'revisions', 'page_setup', 'style_meta']);
        });
    }
};
