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
            $table->string('connection_status', 32)->default('unverified')->after('status')->index();
            $table->string('handshake_endpoint', 255)->nullable()->after('target_url');
            $table->string('target_app_name', 128)->nullable()->after('target_server');
            $table->string('target_framework', 64)->nullable()->after('target_app_name');
            $table->string('target_version', 32)->nullable()->after('target_framework');
            $table->timestamp('last_handshake_at')->nullable()->after('last_ping_at');
            $table->text('last_handshake_message')->nullable()->after('last_error_message');
        });
    }

    public function down(): void
    {
        Schema::table('external_clients', function (Blueprint $table): void {
            $table->dropColumn([
                'connection_status',
                'handshake_endpoint',
                'target_app_name',
                'target_framework',
                'target_version',
                'last_handshake_at',
                'last_handshake_message',
            ]);
        });
    }
};
