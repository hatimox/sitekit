<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables that should have error tracking fields.
     */
    protected array $tables = [
        'databases',
        'web_apps',
        'supervisor_programs',
        'cron_jobs',
        'ssl_certificates',
        'services',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    if (!Schema::hasColumn($table, 'last_error')) {
                        $blueprint->string('last_error')->nullable();
                    }
                    if (!Schema::hasColumn($table, 'last_error_at')) {
                        $blueprint->timestamp('last_error_at')->nullable();
                    }
                    if (!Schema::hasColumn($table, 'suggested_action')) {
                        $blueprint->string('suggested_action')->nullable();
                    }
                });
            }
        }

        // Also add database_health to servers for health check feature
        if (Schema::hasTable('servers')) {
            Schema::table('servers', function (Blueprint $table) {
                if (!Schema::hasColumn('servers', 'database_health')) {
                    $table->json('database_health')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropColumn(['last_error', 'last_error_at', 'suggested_action']);
                });
            }
        }

        if (Schema::hasTable('servers')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->dropColumn('database_health');
            });
        }
    }
};
