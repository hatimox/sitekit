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
        Schema::create('databases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('server_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('team_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('web_app_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');
            $table->enum('type', ['mysql', 'mariadb', 'postgresql'])->default('mariadb');
            $table->enum('status', ['pending', 'active', 'failed'])->default('pending');
            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->unique(['server_id', 'name']);
            $table->index(['team_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('databases');
    }
};
