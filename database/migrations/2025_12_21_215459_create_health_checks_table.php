<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_checks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('web_app_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url');
            $table->string('method')->default('GET');
            $table->integer('expected_status')->default(200);
            $table->string('expected_content')->nullable();
            $table->integer('timeout_seconds')->default(30);
            $table->integer('interval_minutes')->default(5);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('notify_on_failure')->default(true);
            $table->boolean('notify_on_recovery')->default(true);
            $table->string('status')->default('pending'); // pending, up, down
            $table->integer('consecutive_failures')->default(0);
            $table->integer('uptime_percentage')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->integer('last_response_time_ms')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['web_app_id', 'is_enabled']);
            $table->index(['status', 'is_enabled']);
        });

        Schema::create('health_check_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('health_check_id')->constrained()->cascadeOnDelete();
            $table->string('status'); // up, down
            $table->integer('response_time_ms')->nullable();
            $table->integer('status_code')->nullable();
            $table->text('error')->nullable();
            $table->string('checked_from')->default('default'); // region/location
            $table->timestamp('checked_at');

            $table->index(['health_check_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_check_logs');
        Schema::dropIfExists('health_checks');
    }
};
