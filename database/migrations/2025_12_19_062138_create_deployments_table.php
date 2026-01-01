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
        Schema::create('deployments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('web_app_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('team_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignUuid('source_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('repository')->nullable();
            $table->string('branch')->default('main');
            $table->string('commit_hash', 40)->nullable();
            $table->string('commit_message')->nullable();

            $table->enum('trigger', ['manual', 'webhook', 'rollback']);
            $table->enum('status', ['pending', 'cloning', 'building', 'deploying', 'active', 'failed', 'rolled_back'])->default('pending');

            $table->string('release_path')->nullable();
            $table->longText('build_output')->nullable();
            $table->text('error')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();

            $table->index(['web_app_id', 'status']);
            $table->index(['web_app_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deployments');
    }
};
