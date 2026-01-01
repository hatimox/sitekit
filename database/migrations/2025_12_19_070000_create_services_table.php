<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('server_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // php, nodejs, mysql, redis, etc.
            $table->string('version');
            $table->string('status')->default('pending');
            $table->boolean('is_default')->default(false);
            $table->json('configuration')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['server_id', 'type', 'version']);
            $table->index(['server_id', 'type']);
            $table->index(['server_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
