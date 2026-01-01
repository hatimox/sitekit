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
        Schema::create('database_backups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('database_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('server_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('team_id')->constrained()->cascadeOnDelete();

            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->string('filename')->nullable();
            $table->string('path')->nullable();
            $table->string('cloud_path')->nullable();
            $table->string('cloud_storage_driver')->nullable();
            $table->bigInteger('size_bytes')->nullable();
            $table->text('error_message')->nullable();

            $table->enum('trigger', ['manual', 'scheduled'])->default('manual');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(['database_id', 'status']);
            $table->index(['team_id', 'created_at']);
        });

        // Add backup schedule columns to databases table
        Schema::table('databases', function (Blueprint $table) {
            $table->boolean('backup_enabled')->default(false);
            $table->string('backup_schedule')->nullable(); // cron expression
            $table->integer('backup_retention_days')->default(7);
            $table->timestamp('last_backup_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('databases', function (Blueprint $table) {
            $table->dropColumn(['backup_enabled', 'backup_schedule', 'backup_retention_days', 'last_backup_at']);
        });

        Schema::dropIfExists('database_backups');
    }
};
