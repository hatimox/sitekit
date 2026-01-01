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
        Schema::create('config_backups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('config_type'); // nginx, php-fpm, mysql, etc.
            $table->string('file_path'); // Original file path on server
            $table->longText('content'); // Backup content
            $table->string('reason')->nullable(); // Why backup was created
            $table->boolean('is_auto')->default(false); // Auto backup before edit
            $table->timestamps();

            $table->index(['service_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('config_backups');
    }
};
