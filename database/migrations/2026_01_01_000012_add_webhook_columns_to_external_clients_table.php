<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_clients', function (Blueprint $table): void {
            $table->string('webhook_url', 255)->nullable()->after('target_url');
            $table->string('webhook_secret', 64)->nullable()->after('webhook_url');
            $table->timestamp('last_webhook_at')->nullable()->after('last_inbound_endpoint');
            $table->string('last_webhook_status', 32)->nullable()->after('last_webhook_at');
        });
    }

    public function down(): void
    {
        Schema::table('external_clients', function (Blueprint $table): void {
            $table->dropColumn(['webhook_url', 'webhook_secret', 'last_webhook_at', 'last_webhook_status']);
        });
    }
};
