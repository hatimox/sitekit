<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tools', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('server_id')->constrained()->cascadeOnDelete();
            $table->string('name');         // node, npm, yarn, composer, git, certbot, wp-cli
            $table->string('version');      // e.g., "22.11.0", "10.8.2"
            $table->string('path')->nullable(); // e.g., "/usr/bin/node"
            $table->timestamps();

            $table->unique(['server_id', 'name']);
            $table->index(['server_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tools');
    }
};
