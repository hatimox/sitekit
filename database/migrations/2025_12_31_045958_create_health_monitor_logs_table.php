<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_monitor_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('health_monitor_id')->constrained()->cascadeOnDelete();
            $table->string('status'); // up, down
            $table->float('response_time_ms')->nullable();
            $table->integer('status_code')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('checked_at');

            $table->index(['health_monitor_id', 'checked_at']);
            $table->index(['health_monitor_id', 'status', 'checked_at']);
        });

        // Add uptime fields to health_monitors
        Schema::table('health_monitors', function (Blueprint $table) {
            $table->decimal('uptime_24h', 5, 2)->nullable()->after('last_response_time');
            $table->decimal('uptime_7d', 5, 2)->nullable()->after('uptime_24h');
            $table->decimal('uptime_30d', 5, 2)->nullable()->after('uptime_7d');
            $table->float('avg_response_time')->nullable()->after('uptime_30d');
        });
    }

    public function down(): void
    {
        Schema::table('health_monitors', function (Blueprint $table) {
            $table->dropColumn(['uptime_24h', 'uptime_7d', 'uptime_30d', 'avg_response_time']);
        });

        Schema::dropIfExists('health_monitor_logs');
    }
};
