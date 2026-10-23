<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_clients', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 128);
            $table->string('client_slug', 64)->unique();
            $table->string('api_key', 128)->unique()->index();
            $table->string('target_url', 255);
            $table->text('description')->nullable();
            $table->json('allowed_models')->nullable();
            $table->string('status', 32)->default('unknown')->index();
            $table->integer('last_status_code')->nullable();
            $table->integer('last_latency_ms')->nullable();
            $table->string('target_server', 128)->nullable();
            $table->timestamp('last_ping_at')->nullable();
            $table->text('last_error_message')->nullable();
            $table->unsignedInteger('inbound_requests_count')->default(0);
            $table->timestamp('last_inbound_at')->nullable();
            $table->string('last_inbound_endpoint', 128)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_clients');
    }
};
