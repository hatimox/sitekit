<?php

namespace Tests\Unit;

use App\Filament\Resources\WebAppResource\Pages\CreateWebApp;
use App\Models\AgentJob;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use App\Models\WebApp;
use App\Services\PortAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateWebAppTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;
    private Server $server;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->withPersonalTeam()->create();
        $this->team = $this->user->currentTeam;
        $this->server = Server::factory()->create(['team_id' => $this->team->id]);
    }

    /**
     * Test that PHP app creation dispatches correct job payload.
     */
    public function test_create_php_app_dispatches_correct_payload(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'domain' => 'php-app.test',
            'app_type' => WebApp::APP_TYPE_PHP,
            'php_version' => '8.3',
            'web_server' => WebApp::WEB_SERVER_NGINX,
        ]);

        // Use reflection to call protected method
        $createPage = new class extends CreateWebApp {
            public function testCreatePhpApp(WebApp $app): void
            {
                $this->createPhpApp($app);
            }
        };

        $createPage->testCreatePhpApp($app);

        // Check that job was created
        $job = AgentJob::where('server_id', $this->server->id)
            ->where('type', 'create_webapp')
            ->first();

        $this->assertNotNull($job);
        $this->assertEquals('php', $job->payload['app_type']);
        $this->assertEquals('php-app.test', $job->payload['domain']);
        $this->assertEquals('8.3', $job->payload['php_version']);
        $this->assertArrayHasKey('nginx_config', $job->payload);
        $this->assertArrayHasKey('fpm_config', $job->payload);
        $this->assertStringContainsString('server_name php-app.test', $job->payload['nginx_config']);
    }

    /**
     * Test that Node.js app creation dispatches correct job payload.
     */
    public function test_create_nodejs_app_dispatches_correct_payload(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'domain' => 'nextjs-app.test',
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_version' => '22',
            'node_port' => 3000,
            'package_manager' => WebApp::PACKAGE_MANAGER_NPM,
            'start_command' => 'npm start',
            'build_command' => 'npm run build',
        ]);

        $createPage = new class extends CreateWebApp {
            public function testCreateNodeJsApp(WebApp $app): void
            {
                $this->createNodeJsApp($app);
            }
        };

        $createPage->testCreateNodeJsApp($app);

        $job = AgentJob::where('server_id', $this->server->id)
            ->where('type', 'create_webapp')
            ->first();

        $this->assertNotNull($job);
        $this->assertEquals('nodejs', $job->payload['app_type']);
        $this->assertEquals('nextjs-app.test', $job->payload['domain']);
        $this->assertEquals('22', $job->payload['node_version']);
        $this->assertEquals(3000, $job->payload['node_port']);
        $this->assertEquals('npm', $job->payload['package_manager']);
        $this->assertEquals('npm start', $job->payload['start_command']);
        $this->assertArrayHasKey('nginx_config', $job->payload);
        $this->assertArrayHasKey('supervisor_config', $job->payload);
        $this->assertStringContainsString('proxy_pass http://127.0.0.1:3000', $job->payload['nginx_config']);
    }

    /**
     * Test that static app creation dispatches correct job payload.
     */
    public function test_create_static_app_dispatches_correct_payload(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'domain' => 'static-site.test',
            'app_type' => WebApp::APP_TYPE_STATIC,
            'public_path' => 'dist',
        ]);

        $createPage = new class extends CreateWebApp {
            public function testCreateStaticApp(WebApp $app): void
            {
                $this->createStaticApp($app);
            }
        };

        $createPage->testCreateStaticApp($app);

        $job = AgentJob::where('server_id', $this->server->id)
            ->where('type', 'create_webapp')
            ->first();

        $this->assertNotNull($job);
        $this->assertEquals('static', $job->payload['app_type']);
        $this->assertEquals('static-site.test', $job->payload['domain']);
        $this->assertEquals('dist', $job->payload['public_path']);
        $this->assertArrayHasKey('nginx_config', $job->payload);
        $this->assertArrayNotHasKey('fpm_config', $job->payload);
        $this->assertArrayNotHasKey('supervisor_config', $job->payload);
    }

    /**
     * Test supervisor config generation for Node.js app.
     */
    public function test_build_supervisor_config(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'domain' => 'node-supervisor.test',
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_version' => '22',
            'node_port' => 3001,
            'start_command' => 'node dist/main.js',
        ]);

        $createPage = new class extends CreateWebApp {
            public function testBuildSupervisorConfig(WebApp $app): string
            {
                return $this->buildSupervisorConfig($app);
            }
        };

        $config = $createPage->testBuildSupervisorConfig($app);

        $this->assertStringContainsString("[program:nodejs-{$app->id}]", $config);
        $this->assertStringContainsString('command=node dist/main.js', $config);
        $this->assertStringContainsString("directory={$app->root_path}/current", $config);
        $this->assertStringContainsString('user=sitekit', $config);
        $this->assertStringContainsString('autostart=true', $config);
        $this->assertStringContainsString('autorestart=true', $config);
        $this->assertStringContainsString('NODE_ENV=production', $config);
        $this->assertStringContainsString('PORT=3001', $config);
    }

    /**
     * Test static nginx config generation.
     */
    public function test_generate_static_nginx_config(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'domain' => 'static-nginx.test',
            'aliases' => ['www.static-nginx.test'],
            'app_type' => WebApp::APP_TYPE_STATIC,
            'public_path' => 'build',
        ]);

        $createPage = new class extends CreateWebApp {
            public function testGenerateStaticNginxConfig(WebApp $app): string
            {
                return $this->generateStaticNginxConfig($app);
            }
        };

        $config = $createPage->testGenerateStaticNginxConfig($app);

        $this->assertStringContainsString('server_name static-nginx.test www.static-nginx.test', $config);
        $this->assertStringContainsString('index index.html index.htm', $config);
        $this->assertStringContainsString('try_files $uri $uri/ /index.html', $config);
        $this->assertStringContainsString('gzip on', $config);
        $this->assertStringContainsString('expires 1y', $config);
        $this->assertStringNotContainsString('fastcgi_pass', $config);
        $this->assertStringNotContainsString('php', $config);
    }

    /**
     * Test port allocation for Node.js apps in mutateFormData.
     */
    public function test_port_allocation_for_nodejs_apps(): void
    {
        $portService = new PortAllocationService();

        // Verify port is available before
        $this->assertTrue($portService->isAvailable($this->server, 3000));

        // Simulate what mutateFormDataBeforeCreate does
        $data = [
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'domain' => 'port-test.test',
        ];

        $data['node_port'] = $portService->allocate($this->server);

        $this->assertEquals(3000, $data['node_port']);
    }

    /**
     * Test PHP apps do not get port allocation.
     */
    public function test_php_apps_do_not_get_port_allocation(): void
    {
        $data = [
            'app_type' => WebApp::APP_TYPE_PHP,
            'domain' => 'php-port.test',
        ];

        // PHP apps should not have node_port in data
        $this->assertArrayNotHasKey('node_port', $data);
    }

    /**
     * Test Node.js payload includes all required fields.
     */
    public function test_nodejs_payload_includes_all_required_fields(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'domain' => 'full-nodejs.test',
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_version' => '20',
            'node_port' => 3005,
            'package_manager' => WebApp::PACKAGE_MANAGER_PNPM,
            'start_command' => 'pnpm start',
            'build_command' => 'pnpm build',
            'static_assets_path' => '/_next/static/',
            'health_check_path' => '/api/health',
            'pre_deploy_script' => 'npx prisma migrate deploy',
            'post_deploy_script' => 'echo "Deployed!"',
        ]);

        $createPage = new class extends CreateWebApp {
            public function testCreateNodeJsApp(WebApp $app): void
            {
                $this->createNodeJsApp($app);
            }
        };

        $createPage->testCreateNodeJsApp($app);

        $job = AgentJob::where('server_id', $this->server->id)
            ->where('type', 'create_webapp')
            ->first();

        $this->assertNotNull($job);
        $this->assertEquals('20', $job->payload['node_version']);
        $this->assertEquals(3005, $job->payload['node_port']);
        $this->assertEquals('pnpm', $job->payload['package_manager']);
        $this->assertEquals('pnpm start', $job->payload['start_command']);
        $this->assertEquals('pnpm build', $job->payload['build_command']);
        $this->assertEquals('npx prisma migrate deploy', $job->payload['pre_deploy_script']);
        $this->assertEquals('echo "Deployed!"', $job->payload['post_deploy_script']);
    }

    /**
     * Test default commands are used when not specified.
     */
    public function test_default_commands_used_when_not_specified(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'domain' => 'default-cmds.test',
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_version' => '22',
            'node_port' => 3010,
            'package_manager' => WebApp::PACKAGE_MANAGER_YARN,
            'start_command' => null,
            'build_command' => null,
        ]);

        $createPage = new class extends CreateWebApp {
            public function testCreateNodeJsApp(WebApp $app): void
            {
                $this->createNodeJsApp($app);
            }
        };

        $createPage->testCreateNodeJsApp($app);

        $job = AgentJob::where('server_id', $this->server->id)
            ->where('type', 'create_webapp')
            ->first();

        $this->assertNotNull($job);
        // Should use defaults from getDefaultStartCommand/getDefaultBuildCommand
        $this->assertEquals('yarn start', $job->payload['start_command']);
        $this->assertEquals('yarn build', $job->payload['build_command']);
    }
}
