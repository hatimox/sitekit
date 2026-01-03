<?php

namespace Tests\Unit;

use App\Http\Controllers\AgentController;
use App\Models\AgentJob;
use App\Models\Deployment;
use App\Models\Server;
use App\Models\SupervisorProgram;
use App\Models\Team;
use App\Models\User;
use App\Models\WebApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentControllerCallbackTest extends TestCase
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
     * Test PHP app creation callback does not create supervisor program.
     */
    public function test_php_app_callback_does_not_create_supervisor(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'app_type' => WebApp::APP_TYPE_PHP,
            'php_version' => '8.3',
            'status' => WebApp::STATUS_PENDING,
        ]);

        $controller = new class extends AgentController {
            public function testHandleWebAppCreateCallback(array $payload, bool $success, ?string $error): void
            {
                $this->handleWebAppCreateCallback($payload, $success, $error);
            }
        };

        $controller->testHandleWebAppCreateCallback(['app_id' => $app->id], true, null);

        // Refresh the app
        $app->refresh();

        // PHP app should be active but no supervisor program
        $this->assertEquals(WebApp::STATUS_ACTIVE, $app->status);
        $this->assertNull($app->supervisor_program_id);
        $this->assertDatabaseCount('supervisor_programs', 0);
    }

    /**
     * Test Node.js app creation callback creates supervisor program.
     */
    public function test_nodejs_app_callback_creates_supervisor(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_version' => '22',
            'node_port' => 3000,
            'package_manager' => WebApp::PACKAGE_MANAGER_NPM,
            'start_command' => 'npm start',
            'status' => WebApp::STATUS_PENDING,
        ]);

        $controller = new class extends AgentController {
            public function testHandleWebAppCreateCallback(array $payload, bool $success, ?string $error): void
            {
                $this->handleWebAppCreateCallback($payload, $success, $error);
            }
        };

        $controller->testHandleWebAppCreateCallback(['app_id' => $app->id], true, null);

        // Refresh the app
        $app->refresh();

        // Node.js app should have supervisor program
        $this->assertEquals(WebApp::STATUS_ACTIVE, $app->status);
        $this->assertNotNull($app->supervisor_program_id);

        // Check supervisor program was created
        $program = SupervisorProgram::find($app->supervisor_program_id);
        $this->assertNotNull($program);
        $this->assertEquals("nodejs-{$app->id}", $program->name);
        $this->assertEquals('npm start', $program->command);
        $this->assertEquals('production', $program->environment['NODE_ENV']);
        $this->assertEquals('3000', $program->environment['PORT']);
    }

    /**
     * Test Node.js app callback with default start command.
     */
    public function test_nodejs_callback_uses_default_command_if_not_set(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_version' => '22',
            'node_port' => 3001,
            'package_manager' => WebApp::PACKAGE_MANAGER_YARN,
            'start_command' => null, // Not set
            'status' => WebApp::STATUS_PENDING,
        ]);

        $controller = new class extends AgentController {
            public function testHandleWebAppCreateCallback(array $payload, bool $success, ?string $error): void
            {
                $this->handleWebAppCreateCallback($payload, $success, $error);
            }
        };

        $controller->testHandleWebAppCreateCallback(['app_id' => $app->id], true, null);

        $app->refresh();
        $program = SupervisorProgram::find($app->supervisor_program_id);

        $this->assertNotNull($program);
        // Should use default command based on package manager
        $this->assertEquals('yarn start', $program->command);
    }

    /**
     * Test buildNodeCommand handles npm/yarn/pnpm prefixed commands.
     */
    public function test_build_node_command_handles_package_manager_commands(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'package_manager' => WebApp::PACKAGE_MANAGER_NPM,
            'start_command' => 'npm run production',
        ]);

        $controller = new class extends AgentController {
            public function testBuildNodeCommand(WebApp $webApp): string
            {
                return $this->buildNodeCommand($webApp);
            }
        };

        $command = $controller->testBuildNodeCommand($app);
        $this->assertEquals('npm run production', $command);
    }

    /**
     * Test buildNodeCommand handles node commands directly.
     */
    public function test_build_node_command_handles_direct_node_commands(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'package_manager' => WebApp::PACKAGE_MANAGER_NPM,
            'start_command' => 'node dist/main.js',
        ]);

        $controller = new class extends AgentController {
            public function testBuildNodeCommand(WebApp $webApp): string
            {
                return $this->buildNodeCommand($webApp);
            }
        };

        $command = $controller->testBuildNodeCommand($app);
        $this->assertEquals('node dist/main.js', $command);
    }

    /**
     * Test buildNodeCommand prefixes script name with package manager.
     */
    public function test_build_node_command_prefixes_script_names(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'package_manager' => WebApp::PACKAGE_MANAGER_PNPM,
            'start_command' => 'start:prod', // Script name only
        ]);

        $controller = new class extends AgentController {
            public function testBuildNodeCommand(WebApp $webApp): string
            {
                return $this->buildNodeCommand($webApp);
            }
        };

        $command = $controller->testBuildNodeCommand($app);
        $this->assertEquals('pnpm run start:prod', $command);
    }

    /**
     * Test deploy callback restarts supervisor for Node.js apps.
     */
    public function test_deploy_callback_restarts_nodejs_supervisor(): void
    {
        // Create Node.js app with supervisor program
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_version' => '22',
            'node_port' => 3002,
            'status' => WebApp::STATUS_ACTIVE,
        ]);

        $program = SupervisorProgram::create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'web_app_id' => $app->id,
            'name' => "nodejs-{$app->id}",
            'command' => 'npm start',
            'directory' => '/home/sitekit/web/test',
            'user' => 'sitekit',
            'numprocs' => 1,
            'autostart' => true,
            'autorestart' => true,
            'startsecs' => 10,
            'stopwaitsecs' => 30,
            'status' => SupervisorProgram::STATUS_ACTIVE,
        ]);

        $app->update(['supervisor_program_id' => $program->id]);

        // Create a deployment
        $deployment = Deployment::create([
            'web_app_id' => $app->id,
            'team_id' => $this->team->id,
            'user_id' => $this->user->id,
            'trigger' => Deployment::TRIGGER_MANUAL,
            'status' => Deployment::STATUS_DEPLOYING,
            'branch' => 'main',
        ]);

        $controller = new class extends AgentController {
            public function testHandleDeployCallback(array $payload, bool $success, ?string $error, ?string $output): void
            {
                $this->handleDeployCallback($payload, $success, $error, $output);
            }
        };

        $controller->testHandleDeployCallback(
            ['deployment_id' => $deployment->id],
            true,
            null,
            'Deployment successful'
        );

        // Check that supervisor_restart job was created
        $restartJob = AgentJob::where('server_id', $this->server->id)
            ->where('type', 'supervisor_restart')
            ->first();

        $this->assertNotNull($restartJob);
        $this->assertEquals($program->id, $restartJob->payload['program_id']);
        $this->assertEquals($program->name, $restartJob->payload['name']);
    }

    /**
     * Test deploy callback does not restart for PHP apps.
     */
    public function test_deploy_callback_does_not_restart_for_php_apps(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'app_type' => WebApp::APP_TYPE_PHP,
            'php_version' => '8.3',
            'status' => WebApp::STATUS_ACTIVE,
        ]);

        $deployment = Deployment::create([
            'web_app_id' => $app->id,
            'team_id' => $this->team->id,
            'user_id' => $this->user->id,
            'trigger' => Deployment::TRIGGER_MANUAL,
            'status' => Deployment::STATUS_DEPLOYING,
            'branch' => 'main',
        ]);

        $controller = new class extends AgentController {
            public function testHandleDeployCallback(array $payload, bool $success, ?string $error, ?string $output): void
            {
                $this->handleDeployCallback($payload, $success, $error, $output);
            }
        };

        $controller->testHandleDeployCallback(
            ['deployment_id' => $deployment->id],
            true,
            null,
            'Deployment successful'
        );

        // No supervisor_restart job should be created
        $restartJob = AgentJob::where('server_id', $this->server->id)
            ->where('type', 'supervisor_restart')
            ->first();

        $this->assertNull($restartJob);
    }

    /**
     * Test failed Node.js app callback does not create supervisor.
     */
    public function test_failed_nodejs_callback_does_not_create_supervisor(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_version' => '22',
            'node_port' => 3003,
            'status' => WebApp::STATUS_PENDING,
        ]);

        $controller = new class extends AgentController {
            public function testHandleWebAppCreateCallback(array $payload, bool $success, ?string $error): void
            {
                $this->handleWebAppCreateCallback($payload, $success, $error);
            }
        };

        $controller->testHandleWebAppCreateCallback(
            ['app_id' => $app->id],
            false,
            'Failed to create app'
        );

        $app->refresh();

        // App should be failed
        $this->assertEquals(WebApp::STATUS_FAILED, $app->status);
        $this->assertEquals('Failed to create app', $app->error_message);
        $this->assertNull($app->supervisor_program_id);
        $this->assertDatabaseCount('supervisor_programs', 0);
    }

    /**
     * Test supervisor program config generation.
     */
    public function test_supervisor_program_config_generation(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_version' => '22',
            'node_port' => 3004,
            'package_manager' => WebApp::PACKAGE_MANAGER_NPM,
            'start_command' => 'npm start',
            'status' => WebApp::STATUS_PENDING,
        ]);

        $controller = new class extends AgentController {
            public function testHandleWebAppCreateCallback(array $payload, bool $success, ?string $error): void
            {
                $this->handleWebAppCreateCallback($payload, $success, $error);
            }
        };

        $controller->testHandleWebAppCreateCallback(['app_id' => $app->id], true, null);

        $app->refresh();
        $program = SupervisorProgram::find($app->supervisor_program_id);

        // Check the generated config
        $config = $program->generateConfig();

        $this->assertStringContainsString("[program:nodejs-{$app->id}]", $config);
        $this->assertStringContainsString('command=npm start', $config);
        $this->assertStringContainsString('autostart=true', $config);
        $this->assertStringContainsString('autorestart=true', $config);
        $this->assertStringContainsString('NODE_ENV="production"', $config);
        $this->assertStringContainsString('PORT="3004"', $config);
    }

    /**
     * Test monorepo supervisor programs creation.
     */
    public function test_monorepo_supervisor_programs_creation(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_version' => '22',
            'node_port' => 3010,
            'node_processes' => [
                ['name' => 'api', 'command' => 'node api/dist/main.js', 'port' => 3011],
                ['name' => 'web', 'command' => 'npm run start:web', 'port' => 3012],
            ],
            'status' => WebApp::STATUS_PENDING,
        ]);

        $controller = new class extends AgentController {
            public function testCreateNodeSupervisorPrograms(WebApp $webApp): array
            {
                return $this->createNodeSupervisorPrograms($webApp);
            }
        };

        $programs = $controller->testCreateNodeSupervisorPrograms($app);

        $this->assertCount(2, $programs);

        // Check first program (API)
        $this->assertEquals("nodejs-{$app->id}-api", $programs[0]->name);
        $this->assertEquals('node api/dist/main.js', $programs[0]->command);
        $this->assertEquals('3011', $programs[0]->environment['PORT']);

        // Check second program (Web)
        $this->assertEquals("nodejs-{$app->id}-web", $programs[1]->name);
        $this->assertEquals('npm run start:web', $programs[1]->command);
        $this->assertEquals('3012', $programs[1]->environment['PORT']);

        // Verify app is linked to first program
        $app->refresh();
        $this->assertEquals($programs[0]->id, $app->supervisor_program_id);
    }
}
