<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds Node.js application support to the web_apps table.
     * This enables deploying Next.js, NestJS, Express, and other Node.js applications.
     */
    public function up(): void
    {
        Schema::table('web_apps', function (Blueprint $table) {
            // Application type: php (default for backward compatibility), nodejs, static
            $table->string('app_type', 20)->default('php')->after('public_path');

            // Node.js specific configuration
            $table->string('node_version', 10)->nullable()->after('php_version');
            // Values: '24', '22', '20', '18'

            $table->unsignedSmallInteger('node_port')->nullable()->after('node_version');
            // Dynamically assigned port (3000-3999)

            $table->string('package_manager', 10)->default('npm')->after('node_port');
            // Values: 'npm', 'yarn', 'pnpm'

            $table->string('start_command')->nullable()->after('package_manager');
            // Custom start command, e.g., 'npm start', 'node dist/main.js'

            $table->string('build_command')->nullable()->after('start_command');
            // Custom build command, e.g., 'npm run build'

            // v2.0: Monorepo & Production Enhancements
            $table->json('node_processes')->nullable()->after('build_command');
            // Multiple processes: [{"name": "api", "command": "...", "port": 3000, "directory": "apps/api"}]

            $table->json('proxy_routes')->nullable()->after('node_processes');
            // Path-based routing: [{"/api/": 3000}, {"/": 3001}]

            $table->text('pre_deploy_script')->nullable()->after('proxy_routes');
            // Run before deployment (e.g., Prisma migrations)

            $table->text('post_deploy_script')->nullable()->after('pre_deploy_script');
            // Run after deployment (e.g., cache clearing, health checks)

            // v2.1: Framework flexibility
            $table->string('static_assets_path')->nullable()->after('post_deploy_script');
            // Static assets location: '/_next/static/', '/_nuxt/', '/assets/'

            $table->string('health_check_path')->nullable()->after('static_assets_path');
            // Health check endpoint: '/health', '/api/health'

            // Supervisor program relationship for Node.js apps
            $table->foreignUuid('supervisor_program_id')
                ->nullable()
                ->after('source_provider_id')
                ->constrained('supervisor_programs')
                ->nullOnDelete();

            // Index for port lookups (find available ports on server)
            $table->index(['server_id', 'node_port']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('web_apps', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['supervisor_program_id']);

            // Drop index
            $table->dropIndex(['server_id', 'node_port']);

            // Drop columns in reverse order
            $table->dropColumn([
                'supervisor_program_id',
                'health_check_path',
                'static_assets_path',
                'post_deploy_script',
                'pre_deploy_script',
                'proxy_routes',
                'node_processes',
                'build_command',
                'start_command',
                'package_manager',
                'node_port',
                'node_version',
                'app_type',
            ]);
        });
    }
};
