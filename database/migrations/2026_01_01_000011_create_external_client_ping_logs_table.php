<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_client_ping_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('external_client_id')->constrained('external_clients')->cascadeOnDelete();
            $table->string('status', 32)->index();
            $table->integer('status_code')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->string('target_server', 128)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('probed_at')->useCurrent()->index();
            $table->timestamps();

            $table->index(['external_client_id', 'probed_at']);
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_client_ping_logs');
    }
};
