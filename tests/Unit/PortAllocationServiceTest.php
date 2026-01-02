<?php

namespace Tests\Unit;

use App\Models\Server;
use App\Models\Team;
use App\Models\WebApp;
use App\Services\PortAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortAllocationServiceTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;
    private Server $server;
    private PortAllocationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = Team::factory()->create();
        $this->server = Server::factory()->create(['team_id' => $this->team->id]);
        $this->service = new PortAllocationService();
    }

    /**
     * Test allocating a port on an empty server.
     */
    public function test_allocate_returns_first_port_on_empty_server(): void
    {
        $port = $this->service->allocate($this->server);

        $this->assertEquals(3000, $port);
    }

    /**
     * Test allocating a port when some ports are used.
     */
    public function test_allocate_returns_next_available_port(): void
    {
        // Create a web app using port 3000
        WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_port' => 3000,
        ]);

        $port = $this->service->allocate($this->server);

        $this->assertEquals(3001, $port);
    }

    /**
     * Test allocating a port skips used ports.
     */
    public function test_allocate_skips_used_ports(): void
    {
        // Create web apps using ports 3000, 3001, 3002
        for ($p = 3000; $p <= 3002; $p++) {
            WebApp::factory()->create([
                'server_id' => $this->server->id,
                'team_id' => $this->team->id,
                'app_type' => WebApp::APP_TYPE_NODEJS,
                'node_port' => $p,
            ]);
        }

        $port = $this->service->allocate($this->server);

        $this->assertEquals(3003, $port);
    }

    /**
     * Test allocating multiple consecutive ports.
     */
    public function test_allocate_multiple_returns_consecutive_ports(): void
    {
        $ports = $this->service->allocateMultiple($this->server, 3);

        $this->assertCount(3, $ports);
        $this->assertEquals([3000, 3001, 3002], $ports);
    }

    /**
     * Test allocating multiple ports skips used ports.
     */
    public function test_allocate_multiple_skips_used_ports(): void
    {
        // Use port 3001 to break consecutive sequence
        WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_port' => 3001,
        ]);

        $ports = $this->service->allocateMultiple($this->server, 3);

        // Should skip 3001 and find next consecutive block: 3002, 3003, 3004
        $this->assertCount(3, $ports);
        $this->assertEquals([3002, 3003, 3004], $ports);
    }

    /**
     * Test allocate multiple throws exception for invalid count.
     */
    public function test_allocate_multiple_throws_for_zero_count(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->allocateMultiple($this->server, 0);
    }

    /**
     * Test allocate multiple throws exception for too many ports.
     */
    public function test_allocate_multiple_throws_for_over_100_ports(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->allocateMultiple($this->server, 101);
    }

    /**
     * Test isAvailable returns true for unused port.
     */
    public function test_is_available_returns_true_for_unused_port(): void
    {
        $this->assertTrue($this->service->isAvailable($this->server, 3000));
    }

    /**
     * Test isAvailable returns false for used port.
     */
    public function test_is_available_returns_false_for_used_port(): void
    {
        WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_port' => 3000,
        ]);

        $this->assertFalse($this->service->isAvailable($this->server, 3000));
    }

    /**
     * Test isAvailable returns false for ports outside range.
     */
    public function test_is_available_returns_false_for_out_of_range_port(): void
    {
        $this->assertFalse($this->service->isAvailable($this->server, 2999));
        $this->assertFalse($this->service->isAvailable($this->server, 4000));
    }

    /**
     * Test getUsedPorts returns correct ports.
     */
    public function test_get_used_ports_returns_correct_ports(): void
    {
        WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_port' => 3000,
        ]);

        WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_port' => 3005,
        ]);

        $usedPorts = $this->service->getUsedPorts($this->server);

        $this->assertCount(2, $usedPorts);
        $this->assertContains(3000, $usedPorts);
        $this->assertContains(3005, $usedPorts);
    }

    /**
     * Test getUsedPorts includes monorepo ports.
     */
    public function test_get_used_ports_includes_monorepo_ports(): void
    {
        WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_port' => 3000,
            'node_processes' => [
                ['name' => 'api', 'command' => 'node api.js', 'port' => 3001],
                ['name' => 'web', 'command' => 'node web.js', 'port' => 3002],
            ],
        ]);

        $usedPorts = $this->service->getUsedPorts($this->server);

        $this->assertCount(3, $usedPorts);
        $this->assertContains(3000, $usedPorts);
        $this->assertContains(3001, $usedPorts);
        $this->assertContains(3002, $usedPorts);
    }

    /**
     * Test getNextAvailable returns first available port.
     */
    public function test_get_next_available_returns_first_available(): void
    {
        WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_port' => 3000,
        ]);

        $nextPort = $this->service->getNextAvailable($this->server);

        $this->assertEquals(3001, $nextPort);
    }

    /**
     * Test getAvailableCount returns correct count.
     */
    public function test_get_available_count_returns_correct_count(): void
    {
        // No apps = 1000 available ports (3000-3999)
        $this->assertEquals(1000, $this->service->getAvailableCount($this->server));

        // Add one app
        WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_port' => 3000,
        ]);

        $this->assertEquals(999, $this->service->getAvailableCount($this->server));
    }

    /**
     * Test getUsageStats returns correct statistics.
     */
    public function test_get_usage_stats_returns_correct_stats(): void
    {
        WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_port' => 3000,
        ]);

        $stats = $this->service->getUsageStats($this->server);

        $this->assertEquals(1000, $stats['total']);
        $this->assertEquals(1, $stats['used']);
        $this->assertEquals(999, $stats['available']);
        $this->assertEquals(0.1, $stats['usage_percent']);
        $this->assertEquals('3000-3999', $stats['port_range']);
    }

    /**
     * Test isValidPort validates port range.
     */
    public function test_is_valid_port_validates_range(): void
    {
        $this->assertTrue($this->service->isValidPort(3000));
        $this->assertTrue($this->service->isValidPort(3500));
        $this->assertTrue($this->service->isValidPort(3999));

        $this->assertFalse($this->service->isValidPort(2999));
        $this->assertFalse($this->service->isValidPort(4000));
        $this->assertFalse($this->service->isValidPort(80));
    }

    /**
     * Test getPortRange returns correct range.
     */
    public function test_get_port_range_returns_correct_range(): void
    {
        $range = $this->service->getPortRange();

        $this->assertEquals(['min' => 3000, 'max' => 3999], $range);
    }

    /**
     * Test ports are isolated per server.
     */
    public function test_ports_are_isolated_per_server(): void
    {
        $server2 = Server::factory()->create(['team_id' => $this->team->id]);

        // Use port 3000 on first server
        WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_port' => 3000,
        ]);

        // Port 3000 should still be available on second server
        $this->assertTrue($this->service->isAvailable($server2, 3000));

        // Allocating on server2 should return 3000
        $port = $this->service->allocate($server2);
        $this->assertEquals(3000, $port);
    }

    /**
     * Test release logs port release.
     */
    public function test_release_handles_app_with_port(): void
    {
        $app = WebApp::factory()->create([
            'server_id' => $this->server->id,
            'team_id' => $this->team->id,
            'app_type' => WebApp::APP_TYPE_NODEJS,
            'node_port' => 3000,
        ]);

        // Should not throw exception
        $this->service->release($app);

        // Port should still be used (release is just for logging)
        $this->assertContains(3000, $this->service->getUsedPorts($this->server));
    }
}
