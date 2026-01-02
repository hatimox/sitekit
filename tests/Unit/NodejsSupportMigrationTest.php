<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NodejsSupportMigrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that all Node.js support columns exist in web_apps table.
     */
    public function test_web_apps_table_has_nodejs_columns(): void
    {
        // Core app type field
        $this->assertTrue(
            Schema::hasColumn('web_apps', 'app_type'),
            'web_apps table should have app_type column'
        );

        // Node.js configuration fields
        $this->assertTrue(
            Schema::hasColumn('web_apps', 'node_version'),
            'web_apps table should have node_version column'
        );

        $this->assertTrue(
            Schema::hasColumn('web_apps', 'node_port'),
            'web_apps table should have node_port column'
        );

        $this->assertTrue(
            Schema::hasColumn('web_apps', 'package_manager'),
            'web_apps table should have package_manager column'
        );

        $this->assertTrue(
            Schema::hasColumn('web_apps', 'start_command'),
            'web_apps table should have start_command column'
        );

        $this->assertTrue(
            Schema::hasColumn('web_apps', 'build_command'),
            'web_apps table should have build_command column'
        );
    }

    /**
     * Test that monorepo support columns exist.
     */
    public function test_web_apps_table_has_monorepo_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumn('web_apps', 'node_processes'),
            'web_apps table should have node_processes column'
        );

        $this->assertTrue(
            Schema::hasColumn('web_apps', 'proxy_routes'),
            'web_apps table should have proxy_routes column'
        );
    }

    /**
     * Test that deploy hook columns exist.
     */
    public function test_web_apps_table_has_deploy_hook_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumn('web_apps', 'pre_deploy_script'),
            'web_apps table should have pre_deploy_script column'
        );

        $this->assertTrue(
            Schema::hasColumn('web_apps', 'post_deploy_script'),
            'web_apps table should have post_deploy_script column'
        );
    }

    /**
     * Test that framework flexibility columns exist.
     */
    public function test_web_apps_table_has_framework_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumn('web_apps', 'static_assets_path'),
            'web_apps table should have static_assets_path column'
        );

        $this->assertTrue(
            Schema::hasColumn('web_apps', 'health_check_path'),
            'web_apps table should have health_check_path column'
        );
    }

    /**
     * Test that supervisor program relationship column exists.
     */
    public function test_web_apps_table_has_supervisor_program_id(): void
    {
        $this->assertTrue(
            Schema::hasColumn('web_apps', 'supervisor_program_id'),
            'web_apps table should have supervisor_program_id column'
        );
    }

    /**
     * Test that existing PHP-related columns still exist (backward compatibility).
     */
    public function test_web_apps_table_retains_php_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumn('web_apps', 'php_version'),
            'web_apps table should retain php_version column'
        );

        $this->assertTrue(
            Schema::hasColumn('web_apps', 'web_server'),
            'web_apps table should retain web_server column'
        );

        $this->assertTrue(
            Schema::hasColumn('web_apps', 'public_path'),
            'web_apps table should retain public_path column'
        );
    }

    /**
     * Test index exists for port lookups.
     */
    public function test_web_apps_table_has_port_index(): void
    {
        // Get all indexes on web_apps table
        $indexes = collect(
            \DB::select("PRAGMA index_list('web_apps')")
        )->pluck('name')->toArray();

        $this->assertTrue(
            in_array('web_apps_server_id_node_port_index', $indexes),
            'web_apps table should have index on server_id and node_port'
        );
    }
}
