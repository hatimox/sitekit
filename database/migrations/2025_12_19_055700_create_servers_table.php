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
        Schema::create('servers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('ip_address')->nullable();
            $table->string('ssh_port')->default('22');
            $table->enum('status', ['pending', 'provisioning', 'active', 'offline', 'failed'])->default('pending');
            $table->enum('provider', ['custom', 'digitalocean', 'linode', 'vultr', 'hetzner', 'aws'])->default('custom');

            // Agent authentication
            $table->string('agent_token')->unique()->nullable();
            $table->timestamp('agent_token_expires_at')->nullable();
            $table->text('agent_public_key')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();

            // Server status from agent
            $table->json('services_status')->nullable();

            // Server specs (populated by agent)
            $table->string('os_name')->nullable();
            $table->string('os_version')->nullable();
            $table->unsignedSmallInteger('cpu_count')->nullable();
            $table->unsignedInteger('memory_mb')->nullable();
            $table->unsignedInteger('disk_gb')->nullable();

            // Resource alert settings
            $table->decimal('alert_load_threshold', 5, 2)->default(5.00);
            $table->decimal('alert_memory_threshold', 5, 2)->default(90.00);
            $table->decimal('alert_disk_threshold', 5, 2)->default(90.00);
            $table->boolean('resource_alerts_enabled')->default(true);
            $table->boolean('is_load_alert_active')->default(false);
            $table->boolean('is_memory_alert_active')->default(false);
            $table->boolean('is_disk_alert_active')->default(false);
            $table->timestamp('last_resource_alert_at')->nullable();

            $table->timestamps();

            $table->index(['team_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
