<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('health_monitors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('team_id')->constrained()->cascadeOnDelete();

            $table->foreignUuid('server_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('web_app_id')->nullable()->constrained()->cascadeOnDelete();

            $table->enum('type', ['http', 'tcp', 'heartbeat']);

            $table->string('name');
            $table->string('url')->nullable();
            $table->string('host')->nullable();
            $table->integer('port')->nullable();
            $table->string('heartbeat_token')->nullable()->unique();

            $table->integer('interval_seconds')->default(60);
            $table->integer('timeout_seconds')->default(30);
            $table->integer('failure_threshold')->default(3);
            $table->integer('recovery_threshold')->default(2);

            $table->enum('status', ['up', 'down', 'pending', 'paused'])->default('pending');
            $table->timestamp('last_check_at')->nullable();
            $table->timestamp('last_up_at')->nullable();
            $table->timestamp('last_down_at')->nullable();
            $table->integer('consecutive_failures')->default(0);
            $table->integer('consecutive_successes')->default(0);

            $table->boolean('is_active')->default(true);
            $table->boolean('is_up')->default(true);
            $table->float('last_response_time')->nullable();
            $table->integer('last_status_code')->nullable();
            $table->text('last_error')->nullable();
            $table->json('settings')->nullable();

            $table->timestamps();

            $table->index(['team_id', 'is_active']);
            $table->index(['type', 'is_active', 'last_check_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('health_monitors');
    }
};
