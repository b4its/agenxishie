<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah penanda jurnal target terpilih pada rekomendasi platform. Pengguna
 * dapat menetapkan satu jurnal tujuan per proyek; membantu ekspor & fokus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_recommendations', function (Blueprint $table): void {
            $table->boolean('is_selected')->default(false)->after('submission_url')->index();
        });
    }

    public function down(): void
    {
        Schema::table('platform_recommendations', function (Blueprint $table): void {
            $table->dropColumn('is_selected');
        });
    }
};
