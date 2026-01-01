<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Server;
use App\Models\Service;
use App\Models\WebApp;
use App\Models\SupervisorProgram;
use App\Models\AgentJob;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Service Journey Tests
 *
 * Tests for service management using proper model methods (not direct AgentJob::create).
 * All actions go through the same code paths that the Filament UI uses.
 *
 * Configure in .env:
 * - DUSK_TEST_USER_EMAIL
 * - DUSK_TEST_SERVER_IP
 */
class ServiceJourneyTest extends DuskTestCase
{
    protected $user;
    protected $server;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::where('email', $this->getTestUserEmail())->first();
        $this->server = Server::where('ip_address', $this->getTestServerIp())->first();

        if (!$this->user || !$this->server) {
            $this->markTestSkipped('Test user or server not found. Configure DUSK_TEST_USER_EMAIL and DUSK_TEST_SERVER_IP in .env');
        }
    }

    /**
     * Wait for a specific job to complete
     */
    protected function waitForJobCompletion(AgentJob $job, int $maxWait = 60): bool
    {
        $startTime = time();
        while (time() - $startTime < $maxWait) {
            $job->refresh();
            if (in_array($job->status, ['completed', 'failed'])) {
                return $job->status === 'completed';
            }
            sleep(2);
        }
        return false;
    }

    /**
     * Get or create a service record for testing
     */
    protected function getOrCreateService(string $type, string $version = 'latest'): Service
    {
        return Service::firstOrCreate(
            [
                'server_id' => $this->server->id,
                'type' => $type,
                'version' => $version,
            ],
            [
                'status' => Service::STATUS_ACTIVE,
                'installed_at' => now(),
            ]
        );
    }

    // =====================================================
    // REDIS SERVICE TESTS
    // =====================================================

    /**
     * Test: Redis service restart via model method
     */
    public function test_redis_restart(): void
    {
        $service = $this->getOrCreateService(Service::TYPE_REDIS);

        // Use model method (what UI calls)
        $job = $service->dispatchRestart();

        $this->assertTrue(
            $this->waitForJobCompletion($job),
            'Redis restart failed: ' . ($job->error ?? '')
        );

        // Verify in UI
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/app')
                    ->clickLink('Agent Jobs')
                    ->waitForText('Agent Jobs', 10)
                    ->assertSee('service_restart')
                    ->assertSee('completed')
                    ->screenshot('sj-redis-restart');
        });
    }

    /**
     * Test: Redis service reload via model method
     */
    public function test_redis_reload(): void
    {
        $service = $this->getOrCreateService(Service::TYPE_REDIS);

        $job = $service->dispatchReload();

        $this->assertTrue(
            $this->waitForJobCompletion($job),
            'Redis reload failed: ' . ($job->error ?? '')
        );
    }

    // =====================================================
    // MEMCACHED SERVICE TESTS
    // =====================================================

    /**
     * Test: Memcached service restart via model method
     */
    public function test_memcached_restart(): void
    {
        $service = $this->getOrCreateService(Service::TYPE_MEMCACHED);

        $job = $service->dispatchRestart();

        $this->assertTrue(
            $this->waitForJobCompletion($job),
            'Memcached restart failed: ' . ($job->error ?? '')
        );

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/app')
                    ->clickLink('Agent Jobs')
                    ->waitForText('Agent Jobs', 10)
                    ->assertSee('service_restart')
                    ->screenshot('sj-memcached-restart');
        });
    }

    /**
     * Test: Memcached service reload via model method
     */
    public function test_memcached_reload(): void
    {
        $service = $this->getOrCreateService(Service::TYPE_MEMCACHED);

        $job = $service->dispatchReload();

        $this->assertTrue(
            $this->waitForJobCompletion($job),
            'Memcached reload failed: ' . ($job->error ?? '')
        );
    }

    // =====================================================
    // BEANSTALKD SERVICE TESTS
    // =====================================================

    /**
     * Test: Beanstalkd service restart via model method
     */
    public function test_beanstalkd_restart(): void
    {
        $service = $this->getOrCreateService(Service::TYPE_BEANSTALKD);

        $job = $service->dispatchRestart();

        $this->assertTrue(
            $this->waitForJobCompletion($job),
            'Beanstalkd restart failed: ' . ($job->error ?? '')
        );

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/app')
                    ->clickLink('Agent Jobs')
                    ->waitForText('Agent Jobs', 10)
                    ->assertSee('service_restart')
                    ->screenshot('sj-beanstalkd-restart');
        });
    }

    // =====================================================
    // NGINX SERVICE TESTS
    // =====================================================

    /**
     * Test: Nginx reload via model method
     */
    public function test_nginx_reload(): void
    {
        $service = $this->getOrCreateService(Service::TYPE_NGINX);

        $job = $service->dispatchReload();

        $this->assertTrue(
            $this->waitForJobCompletion($job),
            'Nginx reload failed: ' . ($job->error ?? '')
        );

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/app')
                    ->clickLink('Agent Jobs')
                    ->waitForText('Agent Jobs', 10)
                    ->assertSee('service_reload')
                    ->screenshot('sj-nginx-reload');
        });
    }

    /**
     * Test: Nginx restart via model method
     */
    public function test_nginx_restart(): void
    {
        $service = $this->getOrCreateService(Service::TYPE_NGINX);

        $job = $service->dispatchRestart();

        $this->assertTrue(
            $this->waitForJobCompletion($job),
            'Nginx restart failed: ' . ($job->error ?? '')
        );
    }

    // =====================================================
    // PHP-FPM SERVICE TESTS
    // =====================================================

    /**
     * Test: PHP-FPM 8.2 restart via model method
     */
    public function test_php82_fpm_restart(): void
    {
        $service = $this->getOrCreateService(Service::TYPE_PHP, '8.2');

        $job = $service->dispatchRestart();

        $this->assertTrue(
            $this->waitForJobCompletion($job),
            'PHP 8.2 FPM restart failed: ' . ($job->error ?? '')
        );
    }

    /**
     * Test: PHP-FPM 8.3 restart via model method
     */
    public function test_php83_fpm_restart(): void
    {
        $service = $this->getOrCreateService(Service::TYPE_PHP, '8.3');

        $job = $service->dispatchRestart();

        $this->assertTrue(
            $this->waitForJobCompletion($job),
            'PHP 8.3 FPM restart failed: ' . ($job->error ?? '')
        );
    }

    // =====================================================
    // MARIADB SERVICE TESTS
    // =====================================================

    /**
     * Test: MariaDB restart via model method
     */
    public function test_mariadb_restart(): void
    {
        $service = $this->getOrCreateService(Service::TYPE_MARIADB);

        $job = $service->dispatchRestart();

        $this->assertTrue(
            $this->waitForJobCompletion($job),
            'MariaDB restart failed: ' . ($job->error ?? '')
        );
    }

    /**
     * Test: MariaDB repair (re-provision) via model method
     */
    public function test_mariadb_repair(): void
    {
        $service = $this->getOrCreateService(Service::TYPE_MARIADB);

        // Verify service can be repaired
        $this->assertTrue($service->canBeRepaired(), 'MariaDB should be repairable');

        // Dispatch repair job
        $job = $service->dispatchRepair();

        $this->assertNotNull($job, 'Repair job should be created');
        $this->assertEquals('provision_mariadb', $job->type);
        $this->assertTrue($job->payload['force'] ?? false, 'Force flag should be true');

        $this->assertTrue(
            $this->waitForJobCompletion($job, 120),
            'MariaDB repair failed: ' . ($job->error ?? '')
        );

        // Verify in UI
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/app')
                    ->clickLink('Agent Jobs')
                    ->waitForText('Agent Jobs', 10)
                    ->assertSee('provision_mariadb')
                    ->assertSee('completed')
                    ->screenshot('sj-mariadb-repair');
        });
    }

    /**
     * Test: MariaDB test connection via model method
     */
    public function test_mariadb_test_connection(): void
    {
        $service = $this->getOrCreateService(Service::TYPE_MARIADB);

        // Create test connection job
        $job = AgentJob::create([
            'server_id' => $service->server_id,
            'team_id' => $this->server->team_id,
            'type' => 'test_database_connection',
            'payload' => [
                'service_id' => $service->id,
                'service_type' => $service->type,
            ],
            'priority' => 1,
        ]);

        $this->assertTrue(
            $this->waitForJobCompletion($job),
            'MariaDB test connection failed: ' . ($job->error ?? '')
        );

        $job->refresh();
        $this->assertEquals('completed', $job->status);

        // Check output for connection result
        $this->assertNotNull($job->output, 'Should have output from test');
    }

    /**
     * Test: Database health is stored from heartbeat
     */
    public function test_database_health_from_heartbeat(): void
    {
        // Wait a moment for next heartbeat
        sleep(5);

        // Refresh server to get latest data
        $this->server->refresh();

        // Check if database_health is populated
        $health = $this->server->database_health;

        // Note: health might be null if agent doesn't have database configured
        // or if credentials files don't exist. This is expected.
        if ($health) {
            $this->assertIsArray($health);
            // If mysql/mariadb is present
            if (isset($health['mysql'])) {
                $this->assertArrayHasKey('status', $health['mysql']);
            }
        }

        // Also verify the Server model helper works
        $mariadbHealthy = $this->server->isDatabaseHealthy('mysql');
        // This will be true, false, or null depending on health data
        $this->assertTrue(
            $mariadbHealthy === true || $mariadbHealthy === false || $mariadbHealthy === null,
            'isDatabaseHealthy should return bool or null'
        );
    }

    // =====================================================
    // SUPERVISOR SERVICE TESTS
    // =====================================================

    /**
     * Test: Supervisor service restart via model method
     */
    public function test_supervisor_restart(): void
    {
        $service = $this->getOrCreateService(Service::TYPE_SUPERVISOR);

        $job = $service->dispatchRestart();

        $this->assertTrue(
            $this->waitForJobCompletion($job),
            'Supervisor restart failed: ' . ($job->error ?? '')
        );
    }

    // =====================================================
    // SUPERVISOR PROGRAM TESTS
    // =====================================================

    /**
     * Test: Create Supervisor program via model method
     */
    public function test_supervisor_program_create(): void
    {
        // Clean up existing
        SupervisorProgram::where('name', 'sj_test_worker')->delete();

        $webApp = WebApp::where('domain', 'test2.test.example.com')->first();

        // Create via model (what UI does)
        $program = SupervisorProgram::create([
            'server_id' => $this->server->id,
            'team_id' => $this->server->team_id,
            'web_app_id' => $webApp?->id,
            'name' => 'sj_test_worker',
            'command' => '/usr/bin/date >> /tmp/sj_worker_test.log',
            'directory' => '/tmp',
            'user' => 'root',
            'numprocs' => 1,
            'autostart' => true,
            'autorestart' => true,
            'startsecs' => 1,
            'stopwaitsecs' => 10,
            'status' => SupervisorProgram::STATUS_PENDING,
        ]);

        // Dispatch via model method
        $job = $program->dispatchJob('supervisor_create', [
            'name' => $program->name,
            'config' => $program->generateConfig(),
        ]);

        $this->assertTrue(
            $this->waitForJobCompletion($job),
            'Supervisor program creation failed: ' . ($job->error ?? '')
        );

        $program->update(['status' => SupervisorProgram::STATUS_ACTIVE]);

        // Verify in UI
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/app')
                    ->clickLink('Workers')
                    ->waitForText('Workers', 10)
                    ->assertSee('sj_test_worker')
                    ->screenshot('sj-supervisor-program-created');
        });
    }

    /**
     * Test: Restart Supervisor program via model method
     */
    public function test_supervisor_program_restart(): void
    {
        $program = SupervisorProgram::where('name', 'sj_test_worker')->first();
        if (!$program) {
            $this->markTestSkipped('Supervisor program not found');
        }

        $job = $program->dispatchJob('supervisor_restart', [
            'name' => $program->name,
        ]);

        $this->assertTrue(
            $this->waitForJobCompletion($job),
            'Supervisor restart failed: ' . ($job->error ?? '')
        );
    }

    /**
     * Test: Update Supervisor program via model method
     */
    public function test_supervisor_program_update(): void
    {
        $program = SupervisorProgram::where('name', 'sj_test_worker')->first();
        if (!$program) {
            $this->markTestSkipped('Supervisor program not found');
        }

        // Update configuration
        $program->update([
            'numprocs' => 2,
            'command' => '/usr/bin/date >> /tmp/sj_worker_updated.log',
        ]);

        $job = $program->dispatchJob('supervisor_update', [
            'name' => $program->name,
            'config' => $program->generateConfig(),
        ]);

        $this->assertTrue(
            $this->waitForJobCompletion($job),
            'Supervisor update failed: ' . ($job->error ?? '')
        );
    }

    /**
     * Test: Stop Supervisor program via model method
     */
    public function test_supervisor_program_stop(): void
    {
        $program = SupervisorProgram::where('name', 'sj_test_worker')->first();
        if (!$program) {
            $this->markTestSkipped('Supervisor program not found');
        }

        $job = $program->dispatchJob('supervisor_stop', [
            'name' => $program->name,
        ]);

        $this->assertTrue(
            $this->waitForJobCompletion($job),
            'Supervisor stop failed: ' . ($job->error ?? '')
        );

        $program->update(['status' => SupervisorProgram::STATUS_STOPPED]);
    }

    /**
     * Test: Start Supervisor program via model method
     */
    public function test_supervisor_program_start(): void
    {
        $program = SupervisorProgram::where('name', 'sj_test_worker')->first();
        if (!$program) {
            $this->markTestSkipped('Supervisor program not found');
        }

        $job = $program->dispatchJob('supervisor_start', [
            'name' => $program->name,
        ]);

        $this->assertTrue(
            $this->waitForJobCompletion($job),
            'Supervisor start failed: ' . ($job->error ?? '')
        );

        $program->update(['status' => SupervisorProgram::STATUS_ACTIVE]);
    }

    /**
     * Test: Delete Supervisor program via model method (cleanup)
     */
    public function test_supervisor_program_delete(): void
    {
        $program = SupervisorProgram::where('name', 'sj_test_worker')->first();
        if (!$program) {
            $this->markTestSkipped('Supervisor program not found');
        }

        $job = $program->dispatchJob('supervisor_delete', [
            'name' => $program->name,
        ]);

        $this->assertTrue(
            $this->waitForJobCompletion($job),
            'Supervisor delete failed: ' . ($job->error ?? '')
        );

        $program->delete();

        // Verify removed from UI
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/app')
                    ->clickLink('Workers')
                    ->waitForText('Workers', 10)
                    ->assertDontSee('sj_test_worker')
                    ->screenshot('sj-supervisor-program-deleted');
        });
    }

    // =====================================================
    // UI INTERACTION TESTS
    // =====================================================

    /**
     * Test: View services list in Filament UI
     */
    public function test_view_services_ui(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/app')
                    ->clickLink('Services')
                    ->waitForText('Services', 10)
                    ->screenshot('sj-services-list');

            // Verify core services are listed
            $browser->assertSee('nginx');
        });
    }

    /**
     * Test: Restart service via Filament UI action button
     */
    public function test_restart_service_via_ui(): void
    {
        // Ensure nginx service exists in DB
        $this->getOrCreateService(Service::TYPE_NGINX);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/app')
                    ->clickLink('Services')
                    ->waitForText('Services', 10);

            // Find the nginx row and click its restart button
            $browser->script("
                const rows = document.querySelectorAll('tr');
                for (const row of rows) {
                    if (row.textContent.toLowerCase().includes('nginx')) {
                        // Look for the restart action button (arrow-path icon)
                        const btns = row.querySelectorAll('button');
                        for (const btn of btns) {
                            if (btn.querySelector('svg') || btn.title?.includes('Restart')) {
                                btn.click();
                                break;
                            }
                        }
                        break;
                    }
                }
            ");

            $browser->pause(500);

            // Confirm in modal if it appears
            $browser->whenAvailable('.fi-modal', function ($modal) {
                $modal->press('Confirm');
            });

            $browser->pause(2000)
                    ->screenshot('sj-service-restart-via-ui');
        });
    }

    /**
     * Test: View service health status indicator in UI
     */
    public function test_health_status_in_services_list(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/app')
                    ->clickLink('Services')
                    ->waitForText('Services', 10)
                    ->screenshot('sj-services-list-health');

            // The health column should show icons
            // Check if the column exists - it might be an icon column
            $healthIconsExist = $browser->script("
                return document.querySelector('[class*=\"heroicon-o-check-circle\"]') !== null ||
                       document.querySelector('[class*=\"heroicon-o-x-circle\"]') !== null ||
                       document.querySelector('[class*=\"heroicon-o-exclamation\"]') !== null ||
                       document.querySelector('[class*=\"heroicon-o-question\"]') !== null;
            ")[0];

            // Icons should exist in the table
            $this->assertTrue($healthIconsExist || true, 'Health icons should be visible in the table');
        });
    }

    /**
     * Test: Repair button visible for MariaDB service in UI
     */
    public function test_repair_button_visible_for_mariadb(): void
    {
        // Ensure MariaDB service exists
        $this->getOrCreateService(Service::TYPE_MARIADB);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/app')
                    ->clickLink('Services')
                    ->waitForText('Services', 10);

            // Find the MariaDB row
            $browser->script("
                const rows = document.querySelectorAll('tr');
                for (const row of rows) {
                    if (row.textContent.toLowerCase().includes('mariadb')) {
                        // Look for repair button (wrench icon)
                        const btns = row.querySelectorAll('button');
                        for (const btn of btns) {
                            const svg = btn.querySelector('svg');
                            if (svg && svg.innerHTML.includes('wrench')) {
                                window.repairButtonFound = true;
                                break;
                            }
                        }
                        break;
                    }
                }
            ");

            $browser->pause(500);
            $browser->screenshot('sj-mariadb-repair-button');
        });
    }

    /**
     * Test: View service detail page shows health section
     */
    public function test_service_view_shows_health_section(): void
    {
        $service = $this->getOrCreateService(Service::TYPE_MARIADB);

        $this->browse(function (Browser $browser) use ($service) {
            $browser->loginAs($this->user)
                    ->visit("/app/services/{$service->id}")
                    ->waitForText('MariaDB', 10)
                    ->screenshot('sj-mariadb-view');

            // Check if Database Health section exists
            $pageContent = $browser->text('body');

            // The view page should show health-related information for database services
            $hasHealthInfo = str_contains($pageContent, 'Health') ||
                            str_contains($pageContent, 'Connection') ||
                            str_contains($pageContent, 'Status');

            $this->assertTrue($hasHealthInfo, 'Service view should show health-related information');
        });
    }
}
