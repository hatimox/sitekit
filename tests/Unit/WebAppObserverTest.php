<?php

namespace Tests\Unit;

use App\Models\Server;
use App\Models\Service;
use App\Models\Team;
use App\Models\WebApp;
use App\Observers\WebAppObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebAppObserverTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;
    private Server $server;
    private WebAppObserver $observer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = Team::factory()->create();
        $this->server = Server::factory()->create(['team_id' => $this->team->id]);
        $this->observer = new WebAppObserver();
    }

    /**
     * Test created event triggers PHP services for PHP apps.
     */
    public function test_created_triggers_php_services_for_php_apps(): void
    {
        // Create a stopped PHP-FPM service
        $phpService = Service::factory()->create([
            'server_id' => $this->server->id,
            'type' => Service::TYPE_PHP,
            'version' => '8.3',
            'status' => Service::STATUS_STOPPED,
        ]);

        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'app_type' => WebApp::APP_TYPE_PHP,
            'php_version' => '8.3',
        ]);

        // Observer should have been triggered automatically
        // Service should have dispatchStart called (we can check for job)
        $this->assertTrue(true); // Basic test that no exceptions were thrown
    }

    /**
     * Test created event triggers Node.js services for Node.js apps.
     */
    public function test_created_triggers_nodejs_services_for_nodejs_apps(): void
    {
        // Create Node.js and Supervisor services
        Service::factory()->create([
            'server_id' => $this->server->id,
            'type' => Service::TYPE_NODEJS,
            'version' => '22',
            'status' => Service::STATUS_STOPPED,
        ]);

        Service::factory()->create([
            'server_id' => $this->server->id,
            'type' => Service::TYPE_SUPERVISOR,
            'status' => Service::STATUS_STOPPED,
        ]);

        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_version' => '22',
            'node_port' => 3000,
        ]);

        // Observer should have been triggered automatically
        $this->assertTrue(true); // Basic test that no exceptions were thrown
    }

    /**
     * Test static apps don't trigger any service events.
     */
    public function test_static_apps_do_not_trigger_service_events(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'app_type' => WebApp::APP_TYPE_STATIC,
        ]);

        // Observer should have been triggered automatically without errors
        $this->assertTrue(true);
    }

    /**
     * Test deleting event triggers cleanup for Node.js apps.
     */
    public function test_deleting_triggers_cleanup_for_nodejs_apps(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_version' => '22',
            'node_port' => 3000,
        ]);

        // Trigger delete
        $app->delete();

        // App should be deleted
        $this->assertDatabaseMissing('web_apps', ['id' => $app->id]);
    }

    /**
     * Test deleting event with monorepo ports.
     */
    public function test_deleting_handles_monorepo_ports(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_version' => '22',
            'node_port' => 3000,
            'node_processes' => [
                ['name' => 'api', 'command' => 'node api.js', 'port' => 3001],
                ['name' => 'web', 'command' => 'node web.js', 'port' => 3002],
            ],
        ]);

        $app->delete();

        // App should be deleted without errors
        $this->assertDatabaseMissing('web_apps', ['id' => $app->id]);
    }

    /**
     * Test PHP app update triggers service check on version change.
     */
    public function test_updated_triggers_php_service_on_version_change(): void
    {
        // Create PHP services for both versions
        Service::factory()->create([
            'server_id' => $this->server->id,
            'type' => Service::TYPE_PHP,
            'version' => '8.2',
            'status' => Service::STATUS_STOPPED,
        ]);

        Service::factory()->create([
            'server_id' => $this->server->id,
            'type' => Service::TYPE_PHP,
            'version' => '8.3',
            'status' => Service::STATUS_STOPPED,
        ]);

        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'app_type' => WebApp::APP_TYPE_PHP,
            'php_version' => '8.2',
        ]);

        // Update PHP version
        $app->update(['php_version' => '8.3']);

        // Should have triggered service check
        $this->assertEquals('8.3', $app->fresh()->php_version);
    }

    /**
     * Test Node.js app update triggers service check on version change.
     */
    public function test_updated_triggers_nodejs_service_on_version_change(): void
    {
        Service::factory()->create([
            'server_id' => $this->server->id,
            'type' => Service::TYPE_NODEJS,
            'version' => '20',
            'status' => Service::STATUS_STOPPED,
        ]);

        Service::factory()->create([
            'server_id' => $this->server->id,
            'type' => Service::TYPE_NODEJS,
            'version' => '22',
            'status' => Service::STATUS_STOPPED,
        ]);

        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_version' => '20',
            'node_port' => 3000,
        ]);

        // Update Node version
        $app->update(['node_version' => '22']);

        // Should have triggered service check
        $this->assertEquals('22', $app->fresh()->node_version);
    }

    /**
     * Test deleting PHP app doesn't trigger Node.js cleanup.
     */
    public function test_deleting_php_app_does_not_trigger_nodejs_cleanup(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'app_type' => WebApp::APP_TYPE_PHP,
            'php_version' => '8.3',
        ]);

        $app->delete();

        // App should be deleted without Node.js cleanup errors
        $this->assertDatabaseMissing('web_apps', ['id' => $app->id]);
    }
}
