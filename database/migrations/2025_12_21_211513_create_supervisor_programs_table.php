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
        Schema::create('supervisor_programs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('server_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('team_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('web_app_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('command');
            $table->string('directory')->nullable();
            $table->string('user')->default('root');
            $table->integer('numprocs')->default(1);
            $table->boolean('autostart')->default(true);
            $table->boolean('autorestart')->default(true);
            $table->integer('startsecs')->default(1);
            $table->integer('stopwaitsecs')->default(10);
            $table->string('stdout_logfile')->nullable();
            $table->string('stderr_logfile')->nullable();
            $table->json('environment')->nullable();
            $table->string('status')->default('pending'); // pending, active, stopped, failed
            $table->decimal('cpu_percent', 5, 2)->nullable();
            $table->unsignedInteger('memory_mb')->nullable();
            $table->unsignedBigInteger('uptime_seconds')->nullable();
            $table->unsignedInteger('restart_count')->default(0);
            $table->timestamp('metrics_updated_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['server_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supervisor_programs');
    }
};
