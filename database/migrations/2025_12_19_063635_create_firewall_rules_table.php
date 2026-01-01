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
        Schema::create('firewall_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('server_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('team_id')->constrained()->cascadeOnDelete();

            $table->enum('direction', ['in', 'out'])->default('in');
            $table->enum('action', ['allow', 'deny'])->default('allow');
            $table->enum('protocol', ['tcp', 'udp', 'any'])->default('tcp');

            $table->string('port');
            $table->string('from_ip')->default('any');
            $table->string('description')->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_pending_confirmation')->default(false);
            $table->string('confirmation_token')->nullable();
            $table->timestamp('confirmation_expires_at')->nullable();
            $table->text('rollback_reason')->nullable();
            $table->timestamp('rolled_back_at')->nullable();

            $table->integer('order')->default(0);

            $table->timestamps();

            $table->index(['server_id', 'is_active', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('firewall_rules');
    }
};
