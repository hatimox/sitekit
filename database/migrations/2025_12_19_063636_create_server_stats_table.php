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
        Schema::create('server_stats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('server_id')->constrained()->cascadeOnDelete();

            $table->float('cpu_percent')->default(0);
            $table->float('memory_percent')->default(0);
            $table->bigInteger('memory_used_bytes')->nullable();
            $table->bigInteger('memory_total_bytes')->nullable();
            $table->float('disk_percent')->default(0);
            $table->bigInteger('disk_used_bytes')->nullable();
            $table->bigInteger('disk_total_bytes')->nullable();
            $table->float('load_1m')->default(0);
            $table->float('load_5m')->default(0);
            $table->float('load_15m')->default(0);

            $table->timestamp('recorded_at');

            $table->index(['server_id', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_stats');
    }
};
