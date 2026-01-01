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
        Schema::create('ssh_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('team_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('public_key');
            $table->string('fingerprint')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'user_id']);
        });

        Schema::create('server_ssh_key', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('server_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('ssh_key_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'active', 'removing', 'failed'])->default('pending');
            $table->timestamps();

            $table->unique(['server_id', 'ssh_key_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_ssh_key');
        Schema::dropIfExists('ssh_keys');
    }
};
