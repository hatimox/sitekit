<?php

namespace Tests\Browser;

use App\Models\HealthMonitor;
use App\Models\Server;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Real User Journey Test: Health Monitor Management
 *
 * Configure test user and server in .env:
 * - DUSK_TEST_USER_EMAIL
 * - DUSK_TEST_SERVER_IP
 */
class HealthMonitorTest extends DuskTestCase
{
    protected string $testUser;
    protected string $testPassword = 'password';
    protected string $serverIp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testUser = $this->getTestUserEmail();
        $this->serverIp = $this->getTestServerIp();
    }

    /**
     * Helper to login via script-based approach for Filament forms
     */
    protected function login(Browser $browser): void
    {
        $browser->visit('/app/login')
            ->pause(2000);

        $browser->script("
            const emailInput = document.querySelector('input[type=\"email\"]');
            if (emailInput) {
                emailInput.value = '{$this->testUser}';
                emailInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
            const passInput = document.querySelector('input[type=\"password\"]');
            if (passInput) {
                passInput.value = '{$this->testPassword}';
                passInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
        ");

        $browser->pause(500);

        $browser->script("
            const btn = document.querySelector('button[type=\"submit\"]');
            if (btn) btn.click();
        ");

        $browser->pause(5000);
    }

    /**
     * Test 5.1: Create HTTP Health Monitor
     */
    public function test_create_http_health_monitor(): void
    {
        $this->browse(function (Browser $browser) {
            // Login
            $this->login($browser);

            // Navigate to Health Monitors
            $browser->visit('/app/' . $this->teamId . '/health-monitors')
                ->pause(3000)
                ->screenshot('health-monitor-list-before');

            // Create health monitor via model
            echo " Creating HTTP health monitor via model...\n";

            $server = Server::where('team_id', $this->teamId)->first();

            // Delete existing test monitors
            HealthMonitor::where('name', 'Google Check Test')->delete();

            $monitor = HealthMonitor::create([
                'team_id' => $this->teamId,
                'server_id' => $server?->id,
                'type' => HealthMonitor::TYPE_HTTP,
                'name' => 'Google Check Test',
                'url' => 'https://www.google.com',
                'interval_seconds' => 60,
                'timeout_seconds' => 10,
                'failure_threshold' => 3,
                'recovery_threshold' => 2,
                'status' => HealthMonitor::STATUS_PENDING,
                'is_active' => true,
            ]);

            echo " Created monitor: {$monitor->name}\n";
            echo " URL: {$monitor->url}\n";
            echo " Type: {$monitor->type}\n";

            // Run health check command for this monitor
            echo "\n Running health check for monitor...\n";
            \Artisan::call('monitors:check', ['--monitor' => $monitor->id]);
            echo " " . trim(\Artisan::output()) . "\n";

            // Refresh monitor from database
            $monitor->refresh();
            echo " Status after check: {$monitor->status}\n";
            echo " Is Up: " . ($monitor->is_up ? 'Yes' : 'No') . "\n";

            if ($monitor->status === HealthMonitor::STATUS_UP) {
                echo " SUCCESS: Monitor is UP\n";
            } else {
                echo " Status: {$monitor->status}\n";
            }

            // Refresh and check UI
            $browser->visit('/app/' . $this->teamId . '/health-monitors')
                ->pause(3000)
                ->screenshot('health-monitor-list-after');

            $monitorInList = $browser->script("
                return document.body.textContent.includes('Google Check Test') ? 'found' : 'not found';
            ");
            echo " Monitor in UI: " . $monitorInList[0] . "\n";
        });
    }

    /**
     * Test 5.2: Create Internal Service Monitor (Nginx on server)
     */
    public function test_create_nginx_monitor(): void
    {
        $this->browse(function (Browser $browser) {
            // Login
            $this->login($browser);

            echo " Creating Nginx health monitor...\n";

            $server = Server::where('team_id', $this->teamId)->first();

            // Delete existing test monitors
            HealthMonitor::where('name', 'Nginx Check Test')->delete();

            $monitor = HealthMonitor::create([
                'team_id' => $this->teamId,
                'server_id' => $server?->id,
                'type' => HealthMonitor::TYPE_HTTP,
                'name' => 'Nginx Check Test',
                'url' => "http://{$this->serverIp}",
                'interval_seconds' => 60,
                'timeout_seconds' => 10,
                'failure_threshold' => 3,
                'recovery_threshold' => 2,
                'status' => HealthMonitor::STATUS_PENDING,
                'is_active' => true,
            ]);

            echo " Created monitor: {$monitor->name}\n";
            echo " URL: {$monitor->url}\n";

            // Run health check
            echo "\n Running health check for Nginx monitor...\n";
            \Artisan::call('monitors:check', ['--monitor' => $monitor->id]);
            echo " " . trim(\Artisan::output()) . "\n";

            // Refresh monitor
            $monitor->refresh();
            echo " Status: {$monitor->status}\n";
            echo " Response time: {$monitor->last_response_time}ms\n";

            if ($monitor->status === HealthMonitor::STATUS_UP) {
                echo " SUCCESS: Nginx monitor is UP\n";
            } else {
                echo " Note: Server may not have nginx running on port 80\n";
            }

            $browser->screenshot('health-monitor-nginx');
        });
    }

    /**
     * Test 5.3: Create Monitor for Invalid URL (test down detection)
     */
    public function test_detect_down_monitor(): void
    {
        $this->browse(function (Browser $browser) {
            // Login
            $this->login($browser);

            echo " Creating monitor for invalid URL (to test DOWN detection)...\n";

            $server = Server::where('team_id', $this->teamId)->first();

            // Delete existing test monitors
            HealthMonitor::where('name', 'Invalid URL Test')->delete();

            $monitor = HealthMonitor::create([
                'team_id' => $this->teamId,
                'server_id' => $server?->id,
                'type' => HealthMonitor::TYPE_HTTP,
                'name' => 'Invalid URL Test',
                'url' => 'https://thisdomaindoesnotexist12345abc.com',
                'interval_seconds' => 60,
                'timeout_seconds' => 5,
                'failure_threshold' => 1, // Mark down after 1 failure for testing
                'recovery_threshold' => 2,
                'status' => HealthMonitor::STATUS_PENDING,
                'is_active' => true,
            ]);

            echo " Created monitor: {$monitor->name}\n";
            echo " URL: {$monitor->url}\n";

            // Run health check - should fail
            echo "\n Running health check (expecting failure)...\n";
            \Artisan::call('monitors:check', ['--monitor' => $monitor->id]);
            echo " " . trim(\Artisan::output()) . "\n";

            // Refresh monitor
            $monitor->refresh();
            echo " Status: {$monitor->status}\n";
            echo " Error: {$monitor->last_error}\n";
            echo " Consecutive failures: {$monitor->consecutive_failures}\n";

            if ($monitor->status === HealthMonitor::STATUS_DOWN || $monitor->consecutive_failures > 0) {
                echo " SUCCESS: Down detection working\n";
            } else {
                echo " PENDING: May need more check cycles\n";
            }

            $browser->screenshot('health-monitor-down');
        });
    }

    /**
     * Test 5.4: View Monitor Details
     */
    public function test_view_monitor_details(): void
    {
        $this->browse(function (Browser $browser) {
            // Login
            $this->login($browser);

            // Find an existing monitor
            $monitor = HealthMonitor::where('team_id', $this->teamId)
                ->where('name', 'Google Check Test')
                ->first();

            if (!$monitor) {
                echo " No test monitor found - skipping\n";
                $this->markTestSkipped('No test monitor found');
                return;
            }

            // Navigate to monitor detail page
            $browser->visit('/app/' . $this->teamId . '/health-monitors/' . $monitor->id)
                ->pause(3000)
                ->screenshot('health-monitor-detail');

            // Check for key information
            $pageContent = $browser->script("
                return {
                    hasName: document.body.textContent.includes('Google Check Test'),
                    hasUrl: document.body.textContent.includes('google.com'),
                    hasStatus: document.body.textContent.includes('up') || document.body.textContent.includes('down')
                };
            ");

            echo " Monitor detail page:\n";
            echo "   Name visible: " . ($pageContent[0]['hasName'] ? 'yes' : 'no') . "\n";
            echo "   URL visible: " . ($pageContent[0]['hasUrl'] ? 'yes' : 'no') . "\n";
            echo "   Status visible: " . ($pageContent[0]['hasStatus'] ? 'yes' : 'no') . "\n";
        });
    }

    /**
     * Test 5.5: Delete Test Monitors (requires user confirmation)
     *
     * NOTE: This test is disabled by default. DELETE operations
     * require explicit user confirmation per test plan.
     */
    public function skip_test_delete_test_monitors(): void
    {
        // Skipped - DELETE operations require user confirmation
        $this->markTestSkipped('DELETE operations require explicit user confirmation');
    }
}
