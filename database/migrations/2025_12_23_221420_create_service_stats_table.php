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
        Schema::create('service_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('service_id')->constrained()->cascadeOnDelete();
            $table->decimal('cpu_percent', 5, 2)->default(0);
            $table->unsignedInteger('memory_mb')->default(0);
            $table->unsignedBigInteger('uptime_seconds')->default(0);
            $table->timestamp('recorded_at');
            $table->timestamps();

            // Index for efficient querying by service and time
            $table->index(['service_id', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_stats');
    }
};
