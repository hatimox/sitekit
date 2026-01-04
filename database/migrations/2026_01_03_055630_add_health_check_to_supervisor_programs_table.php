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
        Schema::table('supervisor_programs', function (Blueprint $table) {
            $table->boolean('is_healthy')->default(true)->after('status');
            $table->timestamp('last_health_check')->nullable()->after('is_healthy');
            $table->integer('consecutive_failures')->default(0)->after('last_health_check');
            $table->integer('health_check_interval')->default(60)->after('consecutive_failures');
            $table->string('health_check_url')->nullable()->after('health_check_interval');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supervisor_programs', function (Blueprint $table) {
            $table->dropColumn([
                'is_healthy',
                'last_health_check',
                'consecutive_failures',
                'health_check_interval',
                'health_check_url',
            ]);
        });
    }
};
