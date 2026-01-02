<?php

namespace Tests\Unit;

use App\Models\Server;
use App\Models\Team;
use App\Models\WebApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebAppNodejsSupportTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;
    private Server $server;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = Team::factory()->create();
        $this->server = Server::factory()->create(['team_id' => $this->team->id]);
    }

    /**
     * Test app type constants are defined.
     */
    public function test_app_type_constants_are_defined(): void
    {
        $this->assertEquals('php', WebApp::APP_TYPE_PHP);
        $this->assertEquals('nodejs', WebApp::APP_TYPE_NODEJS);
        $this->assertEquals('static', WebApp::APP_TYPE_STATIC);
    }

    /**
     * Test package manager constants are defined.
     */
    public function test_package_manager_constants_are_defined(): void
    {
        $this->assertEquals('npm', WebApp::PACKAGE_MANAGER_NPM);
        $this->assertEquals('yarn', WebApp::PACKAGE_MANAGER_YARN);
        $this->assertEquals('pnpm', WebApp::PACKAGE_MANAGER_PNPM);
    }

    /**
     * Test isPhp() helper method.
     */
    public function test_is_php_helper(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'app_type' => WebApp::APP_TYPE_PHP,
        ]);

        $this->assertTrue($app->isPhp());
        $this->assertFalse($app->isNodeJs());
        $this->assertFalse($app->isStatic());
    }

    /**
     * Test isNodeJs() helper method.
     */
    public function test_is_nodejs_helper(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'app_type' => WebApp::APP_TYPE_NODEJS,
        ]);

        $this->assertFalse($app->isPhp());
        $this->assertTrue($app->isNodeJs());
        $this->assertFalse($app->isStatic());
    }

    /**
     * Test isStatic() helper method.
     */
    public function test_is_static_helper(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'app_type' => WebApp::APP_TYPE_STATIC,
        ]);

        $this->assertFalse($app->isPhp());
        $this->assertFalse($app->isNodeJs());
        $this->assertTrue($app->isStatic());
    }

    /**
     * Test getNodeVersionOptions() returns expected versions.
     */
    public function test_get_node_version_options(): void
    {
        $options = WebApp::getNodeVersionOptions();

        $this->assertIsArray($options);
        $this->assertArrayHasKey('24', $options);
        $this->assertArrayHasKey('22', $options);
        $this->assertArrayHasKey('20', $options);
        $this->assertArrayHasKey('18', $options);
    }

    /**
     * Test getDefaultStartCommand() for npm.
     */
    public function test_get_default_start_command_npm(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'package_manager' => WebApp::PACKAGE_MANAGER_NPM,
        ]);

        $this->assertEquals('npm start', $app->getDefaultStartCommand());
    }

    /**
     * Test getDefaultStartCommand() for yarn.
     */
    public function test_get_default_start_command_yarn(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'package_manager' => WebApp::PACKAGE_MANAGER_YARN,
        ]);

        $this->assertEquals('yarn start', $app->getDefaultStartCommand());
    }

    /**
     * Test getDefaultStartCommand() for pnpm.
     */
    public function test_get_default_start_command_pnpm(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'package_manager' => WebApp::PACKAGE_MANAGER_PNPM,
        ]);

        $this->assertEquals('pnpm start', $app->getDefaultStartCommand());
    }

    /**
     * Test getDefaultBuildCommand() for different package managers.
     */
    public function test_get_default_build_command(): void
    {
        $appNpm = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'package_manager' => WebApp::PACKAGE_MANAGER_NPM,
        ]);
        $this->assertEquals('npm run build', $appNpm->getDefaultBuildCommand());

        $appYarn = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'package_manager' => WebApp::PACKAGE_MANAGER_YARN,
        ]);
        $this->assertEquals('yarn build', $appYarn->getDefaultBuildCommand());

        $appPnpm = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'package_manager' => WebApp::PACKAGE_MANAGER_PNPM,
        ]);
        $this->assertEquals('pnpm build', $appPnpm->getDefaultBuildCommand());
    }

    /**
     * Test getInstallCommand() for different package managers.
     */
    public function test_get_install_command(): void
    {
        $appNpm = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'package_manager' => WebApp::PACKAGE_MANAGER_NPM,
        ]);
        $this->assertEquals('npm ci', $appNpm->getInstallCommand());

        $appYarn = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'package_manager' => WebApp::PACKAGE_MANAGER_YARN,
        ]);
        $this->assertEquals('yarn install --frozen-lockfile', $appYarn->getInstallCommand());

        $appPnpm = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'package_manager' => WebApp::PACKAGE_MANAGER_PNPM,
        ]);
        $this->assertEquals('pnpm install --frozen-lockfile', $appPnpm->getInstallCommand());
    }

    /**
     * Test getDefaultBuildScript() returns PHP script for PHP apps.
     */
    public function test_get_default_build_script_php(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'app_type' => WebApp::APP_TYPE_PHP,
        ]);

        $script = $app->getDefaultBuildScript();

        $this->assertStringContainsString('composer install', $script);
        $this->assertStringContainsString('artisan', $script);
    }

    /**
     * Test getDefaultBuildScript() returns Node.js script for Node.js apps.
     */
    public function test_get_default_build_script_nodejs(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'package_manager' => WebApp::PACKAGE_MANAGER_NPM,
        ]);

        $script = $app->getDefaultBuildScript();

        $this->assertStringContainsString('npm ci', $script);
        $this->assertStringContainsString('npm run build', $script);
        $this->assertStringNotContainsString('composer', $script);
    }

    /**
     * Test getDefaultBuildScript() returns static script for static apps.
     */
    public function test_get_default_build_script_static(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'app_type' => WebApp::APP_TYPE_STATIC,
            'package_manager' => WebApp::PACKAGE_MANAGER_NPM,
        ]);

        $script = $app->getDefaultBuildScript();

        $this->assertStringContainsString('package.json', $script);
        $this->assertStringContainsString('npm run build', $script);
    }

    /**
     * Test Node.js fields can be saved and retrieved.
     */
    public function test_nodejs_fields_can_be_saved(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_version' => '22',
            'node_port' => 3000,
            'package_manager' => WebApp::PACKAGE_MANAGER_PNPM,
            'start_command' => 'node server.js',
            'build_command' => 'pnpm run build:prod',
            'static_assets_path' => '/_next/static/',
            'health_check_path' => '/api/health',
        ]);

        $app->refresh();

        $this->assertEquals(WebApp::APP_TYPE_NODEJS, $app->app_type);
        $this->assertEquals('22', $app->node_version);
        $this->assertEquals(3000, $app->node_port);
        $this->assertEquals(WebApp::PACKAGE_MANAGER_PNPM, $app->package_manager);
        $this->assertEquals('node server.js', $app->start_command);
        $this->assertEquals('pnpm run build:prod', $app->build_command);
        $this->assertEquals('/_next/static/', $app->static_assets_path);
        $this->assertEquals('/api/health', $app->health_check_path);
    }

    /**
     * Test node_processes JSON field works correctly.
     */
    public function test_node_processes_json_field(): void
    {
        $processes = [
            ['name' => 'api', 'command' => 'node api/server.js', 'port' => 3000],
            ['name' => 'web', 'command' => 'node web/server.js', 'port' => 3001],
        ];

        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_processes' => $processes,
        ]);

        $app->refresh();

        $this->assertIsArray($app->node_processes);
        $this->assertCount(2, $app->node_processes);
        $this->assertEquals('api', $app->node_processes[0]['name']);
        $this->assertEquals(3001, $app->node_processes[1]['port']);
    }

    /**
     * Test proxy_routes JSON field works correctly.
     */
    public function test_proxy_routes_json_field(): void
    {
        $routes = [
            ['/api/' => 3000],
            ['/' => 3001],
        ];

        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'proxy_routes' => $routes,
        ]);

        $app->refresh();

        $this->assertIsArray($app->proxy_routes);
        $this->assertCount(2, $app->proxy_routes);
    }

    /**
     * Test pre_deploy_script and post_deploy_script can be saved.
     */
    public function test_deploy_hook_scripts_can_be_saved(): void
    {
        $preScript = 'npx prisma migrate deploy';
        $postScript = 'curl -X POST https://example.com/webhook';

        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'pre_deploy_script' => $preScript,
            'post_deploy_script' => $postScript,
        ]);

        $app->refresh();

        $this->assertEquals($preScript, $app->pre_deploy_script);
        $this->assertEquals($postScript, $app->post_deploy_script);
    }

    /**
     * Test default app_type is php for backward compatibility.
     */
    public function test_default_app_type_is_php(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
        ]);

        $this->assertEquals(WebApp::APP_TYPE_PHP, $app->app_type);
    }
}
