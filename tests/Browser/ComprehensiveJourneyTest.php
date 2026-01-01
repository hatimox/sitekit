<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Server;
use App\Models\Service;
use App\Models\WebApp;
use App\Models\Database;
use App\Models\DatabaseUser;
use App\Models\SupervisorProgram;
use App\Models\SslCertificate;
use App\Models\AgentJob;
use App\Services\ConfigGenerator\NginxConfigGenerator;
use App\Services\ConfigGenerator\PhpFpmConfigGenerator;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Comprehensive Journey Tests
 *
 * Tests complete user journeys through the Filament UI and backend model methods.
 * NO direct AgentJob::create() calls - all actions go through proper channels.
 *
 * Test Scenarios:
 * 1. test1.test.example.com - PHP 8.2 with SSL
 * 2. test2.test.example.com - PHP 8.3 with SSL
 * 3. Service management via model methods
 * 4. Supervisor program management via model methods
 */
class ComprehensiveJourneyTest extends DuskTestCase
{
    protected $user;
    protected $server;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::where('email', $this->getTestUserEmail())->first();
        $this->server = Server::where('ip_address', $this->getTestServerIp())->first();

        if (!$this->user || !$this->server) {
            $this->markTestSkipped('Demo user or test server not found. Configure DUSK_TEST_USER_EMAIL and DUSK_TEST_SERVER_IP in .env');
        }
    }

    /**
     * Wait for a specific job to complete by polling
     */
    protected function waitForJobCompletion(AgentJob $job, int $maxWait = 120): bool
    {
        $startTime = time();
        while (time() - $startTime < $maxWait) {
            $job->refresh();
            if (in_array($job->status, ['completed', 'failed'])) {
                return $job->status === 'completed';
            }
            sleep(3);
        }
        return false;
    }

    /**
     * Wait for the latest job of a type to complete
     */
    protected function waitForLatestJob(string $jobType, int $maxWait = 60): ?AgentJob
    {
        $startTime = time();
        while (time() - $startTime < $maxWait) {
            $job = AgentJob::where('server_id', $this->server->id)
                ->where('type', $jobType)
                ->orderBy('created_at', 'desc')
                ->first();

            if ($job && in_array($job->status, ['completed', 'failed'])) {
                return $job;
            }
            sleep(2);
        }
        return null;
    }

    // =====================================================
    // WEBAPP JOURNEYS - Two apps with different PHP versions
    // =====================================================

    /**
     * Journey: Create test1.test.example.com with PHP 8.2 via model method
     * (Uses same code path as Filament UI form submission)
     */
    public function test_01_create_webapp_test1_php82(): void
    {
        // Clean up any existing
        $existing = WebApp::where('domain', 'test1.test.example.com')->first();
        if ($existing) {
            SslCertificate::where('web_app_id', $existing->id)->delete();
            $existing->delete();
        }

        // Create webapp via model (same as what Filament UI does internally)
        // system_user defaults to 'sitekit' via generateSystemUser()
        $webApp = WebApp::create([
            'server_id' => $this->server->id,
            'team_id' => $this->server->team_id,
            'name' => 'Test1App',
            'domain' => 'test1.test.example.com',
            'php_version' => '8.2',
            'web_server' => WebApp::WEB_SERVER_NGINX,
            'public_path' => 'public',
            'status' => WebApp::STATUS_PENDING,
            'ssl_status' => WebApp::SSL_NONE,
        ]);

        // Generate configs using the same generators the UI uses
        $nginxGen = new NginxConfigGenerator();
        $phpFpmGen = new PhpFpmConfigGenerator();
        $nginxConfig = $nginxGen->generate($webApp);
        $phpFpmConfig = $phpFpmGen->generate($webApp);

        // Dispatch creation job (same payload as Filament UI)
        $job = $webApp->dispatchJob('create_webapp', [
            'app_id' => $webApp->id,
            'domain' => $webApp->domain,
            'aliases' => $webApp->aliases ?? [],
            'username' => $webApp->system_user,
            'root_path' => $webApp->root_path,
            'public_path' => $webApp->public_path ?? 'public',
            'php_version' => $webApp->php_version,
            'app_type' => 'php',
            'nginx_config' => $nginxConfig,
            'fpm_config' => $phpFpmConfig,
            'deploy_public_key' => $webApp->deploy_public_key ?? '',
        ], 1);

        // Wait for completion
        $this->assertTrue(
            $this->waitForJobCompletion($job),
            'WebApp creation failed: ' . ($job->error ?? 'timeout')
        );

        $webApp->update(['status' => WebApp::STATUS_ACTIVE]);

        // Verify in UI
        $this->browse(function (Browser $browser) use ($webApp) {
            $browser->loginAs($this->user)
                    ->visit('/app')
                    ->clickLink('Web Apps')
                    ->waitForText('Web Apps', 10)
                    ->assertSee('test1.test.example.com')
                    ->assertSee('8.2')
                    ->screenshot('cj01-webapp-created');
        });

        $this->assertEquals('8.2', $webApp->php_version);
    }

    /**
     * Journey: Create test2.test.example.com with PHP 8.3 via model method
     * (Uses same code path as Filament UI form submission)
     */
    public function test_02_create_webapp_test2_php83(): void
    {
        // Clean up any existing
        $existing = WebApp::where('domain', 'test2.test.example.com')->first();
        if ($existing) {
            SslCertificate::where('web_app_id', $existing->id)->delete();
            $existing->delete();
        }

        // Create webapp via model (same as what Filament UI does internally)
        // system_user defaults to 'sitekit' via generateSystemUser()
        $webApp = WebApp::create([
            'server_id' => $this->server->id,
            'team_id' => $this->server->team_id,
            'name' => 'Test2App',
            'domain' => 'test2.test.example.com',
            'php_version' => '8.3',
            'web_server' => WebApp::WEB_SERVER_NGINX,
            'public_path' => 'public',
            'status' => WebApp::STATUS_PENDING,
            'ssl_status' => WebApp::SSL_NONE,
        ]);

        // Generate configs
        $nginxGen = new NginxConfigGenerator();
        $phpFpmGen = new PhpFpmConfigGenerator();
        $nginxConfig = $nginxGen->generate($webApp);
        $phpFpmConfig = $phpFpmGen->generate($webApp);

        // Dispatch creation job (same payload as Filament UI)
        $job = $webApp->dispatchJob('create_webapp', [
            'app_id' => $webApp->id,
            'domain' => $webApp->domain,
            'aliases' => $webApp->aliases ?? [],
            'username' => $webApp->system_user,
            'root_path' => $webApp->root_path,
            'public_path' => $webApp->public_path ?? 'public',
            'php_version' => $webApp->php_version,
            'app_type' => 'php',
            'nginx_config' => $nginxConfig,
            'fpm_config' => $phpFpmConfig,
            'deploy_public_key' => $webApp->deploy_public_key ?? '',
        ], 1);

        // Wait for completion
        $this->assertTrue(
            $this->waitForJobCompletion($job),
            'WebApp creation failed: ' . ($job->error ?? 'timeout')
        );

        $webApp->update(['status' => WebApp::STATUS_ACTIVE]);

        // Verify in UI
        $this->browse(function (Browser $browser) use ($webApp) {
            $browser->loginAs($this->user)
                    ->visit('/app')
                    ->clickLink('Web Apps')
                    ->waitForText('Web Apps', 10)
                    ->assertSee('test2.test.example.com')
                    ->assertSee('8.3')
                    ->screenshot('cj02-webapp-created');
        });

        $this->assertEquals('8.3', $webApp->php_version);
    }

    /**
     * Journey: Deploy code to test1 via model method (prerequisite for SSL)
     * Uses public Laravel repo (no SSH keys required)
     */
    public function test_03_deploy_test1_via_model(): void
    {
        $webApp = WebApp::where('domain', 'test1.test.example.com')->first();
        if (!$webApp) {
            $this->markTestSkipped('test1.test.example.com not found');
        }

        // Configure for public Laravel repo (HTTPS - no SSH keys needed)
        $webApp->update([
            'repository' => 'https://github.com/laravel/laravel.git',
            'branch' => '11.x',
        ]);

        // Generate unique commit hash for this deployment (12 chars used by handler)
        $commitHash = substr(md5('test1-' . microtime(true)), 0, 40);

        // Use dispatchJob directly with proper payload for public repo
        $job = $webApp->dispatchJob('deploy', [
            'deployment_id' => 'test-deploy-' . time(),
            'app_path' => $webApp->root_path,
            'username' => $webApp->system_user,
            'repository' => 'https://github.com/laravel/laravel.git', // HTTPS URL
            'branch' => '11.x',
            'commit_hash' => $commitHash, // Unique hash for each run
            'ssh_url' => '', // Empty - use HTTPS
            'deploy_key' => '', // No key needed for public repo
            'shared_files' => ['.env'],
            'shared_directories' => ['storage'],
            'build_script' => 'echo "Test deployment"', // Simple script for testing
            'php_version' => $webApp->php_version,
        ], 1);

        $this->assertTrue(
            $this->waitForJobCompletion($job, 180),
            'Deployment failed: ' . ($job->error ?? 'timeout')
        );

        $webApp->update(['status' => WebApp::STATUS_ACTIVE]);
    }

    /**
     * Journey: Deploy code to test2 via model method
     */
    public function test_04_deploy_test2_via_model(): void
    {
        $webApp = WebApp::where('domain', 'test2.test.example.com')->first();
        if (!$webApp) {
            $this->markTestSkipped('test2.test.example.com not found');
        }

        // Configure for public Laravel repo
        $webApp->update([
            'repository' => 'https://github.com/laravel/laravel.git',
            'branch' => '11.x',
        ]);

        // Generate unique commit hash for this deployment
        $commitHash = substr(md5('test2-' . microtime(true)), 0, 40);

        // Use dispatchJob directly with proper payload
        $job = $webApp->dispatchJob('deploy', [
            'deployment_id' => 'test-deploy-' . time(),
            'app_path' => $webApp->root_path,
            'username' => $webApp->system_user,
            'repository' => 'https://github.com/laravel/laravel.git',
            'branch' => '11.x',
            'commit_hash' => $commitHash, // Unique hash for each run
            'ssh_url' => '',
            'deploy_key' => '',
            'shared_files' => ['.env'],
            'shared_directories' => ['storage'],
            'build_script' => 'echo "Test deployment"',
            'php_version' => $webApp->php_version,
        ], 1);

        $this->assertTrue(
            $this->waitForJobCompletion($job, 180),
            'Deployment failed: ' . ($job->error ?? 'timeout')
        );

        $webApp->update(['status' => WebApp::STATUS_ACTIVE]);
    }

    /**
     * Journey: Issue SSL for test1 via model method
     * Note: Requires DNS to point directly to server (not through Cloudflare proxy)
     * If behind Cloudflare, HTTP-01 challenge won't work - need DNS-01 challenge instead
     */
    public function test_05_issue_ssl_test1_via_model(): void
    {
        $webApp = WebApp::where('domain', 'test1.test.example.com')->first();
        if (!$webApp) {
            $this->markTestSkipped('test1.test.example.com not found');
        }

        // Check if DNS points directly to our server (not Cloudflare)
        $serverIp = $this->server->ip_address;
        $dnsIps = gethostbynamel($webApp->domain) ?: [];
        if (!in_array($serverIp, $dnsIps)) {
            $this->markTestSkipped("SSL test skipped: DNS for {$webApp->domain} points to " . implode(', ', $dnsIps) . " (expected {$serverIp}). Domains behind Cloudflare require DNS-01 challenge.");
        }

        // Create SSL certificate record
        $cert = SslCertificate::firstOrCreate(
            ['web_app_id' => $webApp->id, 'domain' => $webApp->domain],
            ['status' => SslCertificate::STATUS_PENDING]
        );

        // Skip if already active
        if ($cert->status === SslCertificate::STATUS_ACTIVE) {
            $this->assertTrue(true, 'SSL already active');
            return;
        }

        // Issue SSL via model method (same as UI)
        $job = $cert->dispatchIssue();

        $this->assertTrue(
            $this->waitForJobCompletion($job, 120),
            'SSL issuance failed: ' . ($job->error ?? 'timeout')
        );

        $cert->refresh();
        $this->assertEquals(SslCertificate::STATUS_ACTIVE, $cert->status);
        $webApp->update(['ssl_status' => WebApp::SSL_ACTIVE]);
    }

    /**
     * Journey: Issue SSL for test2 via model method
     */
    public function test_06_issue_ssl_test2_via_model(): void
    {
        $webApp = WebApp::where('domain', 'test2.test.example.com')->first();
        if (!$webApp) {
            $this->markTestSkipped('test2.test.example.com not found');
        }

        // Check if DNS points directly to our server
        $serverIp = $this->server->ip_address;
        $dnsIps = gethostbynamel($webApp->domain) ?: [];
        if (!in_array($serverIp, $dnsIps)) {
            $this->markTestSkipped("SSL test skipped: DNS for {$webApp->domain} doesn't point to server ({$serverIp}). Cloudflare proxy requires DNS-01 challenge.");
        }

        // Create SSL certificate record
        $cert = SslCertificate::firstOrCreate(
            ['web_app_id' => $webApp->id, 'domain' => $webApp->domain],
            ['status' => SslCertificate::STATUS_PENDING]
        );

        // Skip if already active
        if ($cert->status === SslCertificate::STATUS_ACTIVE) {
            $this->assertTrue(true, 'SSL already active');
            return;
        }

        // Issue SSL via model method
        $job = $cert->dispatchIssue();

        $this->assertTrue(
            $this->waitForJobCompletion($job, 120),
            'SSL issuance failed: ' . ($job->error ?? 'timeout')
        );

        $cert->refresh();
        $this->assertEquals(SslCertificate::STATUS_ACTIVE, $cert->status);
        $webApp->update(['ssl_status' => WebApp::SSL_ACTIVE]);
    }

    /**
     * Journey: Verify both apps have different PHP versions in UI
     */
    public function test_07_verify_php_versions_in_ui(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/app')
                    ->waitForText('Dashboard', 15);

            // Navigate to Web Apps
            $browser->clickLink('Web Apps')
                    ->waitForText('Web Apps', 10)
                    ->screenshot('cj07-01-webapps-list');

            // Should see both apps with different PHP versions
            $browser->assertSee('Test1App')
                    ->assertSee('Test2App')
                    ->assertSee('8.2')  // test1's PHP version
                    ->assertSee('8.3'); // test2's PHP version
        });
    }

    // =====================================================
    // SERVICE JOURNEYS - Using model methods
    // =====================================================

    /**
     * Journey: Restart Redis via Service model method
     */
    public function test_10_restart_redis_via_model(): void
    {
        // Get or create service record
        $service = Service::firstOrCreate(
            [
                'server_id' => $this->server->id,
                'type' => Service::TYPE_REDIS,
            ],
            [
                'version' => 'latest',
                'status' => Service::STATUS_ACTIVE,
            ]
        );

        // Dispatch restart via model method (what UI calls)
        $job = $service->dispatchRestart();

        $this->assertTrue($this->waitForJobCompletion($job, 60), 'Redis restart failed: ' . ($job->error ?? ''));

        // Verify in UI
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/app')
                    ->clickLink('Agent Jobs')
                    ->waitForText('Agent Jobs', 10)
                    ->assertSee('service_restart')
                    ->assertSee('completed')
                    ->screenshot('cj10-redis-restart');
        });
    }

    /**
     * Journey: Reload Memcached via Service model method
     */
    public function test_11_restart_memcached_via_model(): void
    {
        // Note: Memcached doesn't support reload, only restart
        $service = Service::firstOrCreate(
            [
                'server_id' => $this->server->id,
                'type' => Service::TYPE_MEMCACHED,
            ],
            [
                'version' => 'latest',
                'status' => Service::STATUS_ACTIVE,
            ]
        );

        $job = $service->dispatchRestart(); // Use restart instead of reload

        $this->assertTrue($this->waitForJobCompletion($job, 60), 'Memcached restart failed: ' . ($job->error ?? ''));
    }

    /**
     * Journey: Restart Beanstalkd via Service model method
     */
    public function test_12_restart_beanstalkd_via_model(): void
    {
        $service = Service::firstOrCreate(
            [
                'server_id' => $this->server->id,
                'type' => Service::TYPE_BEANSTALKD,
            ],
            [
                'version' => 'latest',
                'status' => Service::STATUS_ACTIVE,
            ]
        );

        $job = $service->dispatchRestart();

        $this->assertTrue($this->waitForJobCompletion($job, 60), 'Beanstalkd restart failed: ' . ($job->error ?? ''));
    }

    /**
     * Journey: Reload Nginx via Service model method
     */
    public function test_13_reload_nginx_via_model(): void
    {
        $service = Service::firstOrCreate(
            [
                'server_id' => $this->server->id,
                'type' => Service::TYPE_NGINX,
            ],
            [
                'version' => 'latest',
                'status' => Service::STATUS_ACTIVE,
            ]
        );

        $job = $service->dispatchReload();

        $this->assertTrue($this->waitForJobCompletion($job, 60), 'Nginx reload failed: ' . ($job->error ?? ''));
    }

    /**
     * Journey: Restart PHP-FPM 8.2 via Service model method
     */
    public function test_14_restart_php82_fpm_via_model(): void
    {
        $service = Service::firstOrCreate(
            [
                'server_id' => $this->server->id,
                'type' => Service::TYPE_PHP,
                'version' => '8.2',
            ],
            [
                'status' => Service::STATUS_ACTIVE,
            ]
        );

        $job = $service->dispatchRestart();

        $this->assertTrue($this->waitForJobCompletion($job, 60), 'PHP 8.2 FPM restart failed: ' . ($job->error ?? ''));
    }

    /**
     * Journey: Restart PHP-FPM 8.3 via Service model method
     */
    public function test_15_restart_php83_fpm_via_model(): void
    {
        $service = Service::firstOrCreate(
            [
                'server_id' => $this->server->id,
                'type' => Service::TYPE_PHP,
                'version' => '8.3',
            ],
            [
                'status' => Service::STATUS_ACTIVE,
            ]
        );

        $job = $service->dispatchRestart();

        $this->assertTrue($this->waitForJobCompletion($job, 60), 'PHP 8.3 FPM restart failed: ' . ($job->error ?? ''));
    }

    /**
     * Journey: Restart service via Filament UI
     */
    public function test_16_restart_service_via_ui(): void
    {
        // Ensure we have a service record for nginx
        Service::firstOrCreate(
            [
                'server_id' => $this->server->id,
                'type' => Service::TYPE_NGINX,
            ],
            [
                'version' => 'latest',
                'status' => Service::STATUS_ACTIVE,
            ]
        );

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/app')
                    ->waitForText('Dashboard', 15);

            // Navigate to Services
            $browser->clickLink('Services')
                    ->waitForText('Services', 10)
                    ->screenshot('cj16-01-services-list');

            // Find nginx row and click Restart action
            // Using JavaScript to find and click the action button
            $browser->script("
                const rows = document.querySelectorAll('tr');
                for (const row of rows) {
                    if (row.textContent.includes('nginx') || row.textContent.includes('Nginx')) {
                        const restartBtn = row.querySelector('button[title*=\"Restart\"], button:has(svg[class*=\"arrow-path\"])');
                        if (restartBtn) {
                            restartBtn.click();
                            break;
                        }
                    }
                }
            ");

            $browser->pause(1000)
                    ->screenshot('cj16-02-restart-clicked');

            // Confirm if modal appears
            $browser->whenAvailable('.fi-modal', function ($modal) {
                $modal->press('Confirm');
            });

            $browser->pause(2000)
                    ->screenshot('cj16-03-restart-confirmed');
        });

        // Wait for job
        $job = $this->waitForLatestJob('service_restart', 60);
        $this->assertNotNull($job, 'Service restart job did not complete');
    }

    // =====================================================
    // SUPERVISOR PROGRAM JOURNEYS - Using model methods
    // =====================================================

    /**
     * Journey: Create Supervisor program via model method
     */
    public function test_20_create_supervisor_program_via_model(): void
    {
        // Clean up existing
        SupervisorProgram::where('name', 'test_worker')->delete();

        $webApp = WebApp::where('domain', 'test1.test.example.com')->first();

        // Create program via model - use simple long-running command for testing
        // (don't use Laravel artisan since vendor may not be installed)
        $program = SupervisorProgram::create([
            'server_id' => $this->server->id,
            'team_id' => $this->server->team_id,
            'web_app_id' => $webApp?->id,
            'name' => 'test_worker',
            'command' => '/bin/bash -c "while true; do echo heartbeat; sleep 30; done"',
            'directory' => '/tmp',
            'user' => 'root',
            'numprocs' => 1,
            'autostart' => true,
            'autorestart' => true,
            'startsecs' => 1,
            'stopwaitsecs' => 10,
            'status' => SupervisorProgram::STATUS_PENDING,
        ]);

        // Dispatch via model method (what UI does)
        $job = $program->dispatchJob('supervisor_create', [
            'name' => $program->name,
            'config' => $program->generateConfig(),
        ]);

        $this->assertTrue($this->waitForJobCompletion($job, 60), 'Supervisor program creation failed: ' . ($job->error ?? ''));

        $program->update(['status' => SupervisorProgram::STATUS_ACTIVE]);

        // Verify in UI
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/app')
                    ->clickLink('Workers')
                    ->waitForText('Workers', 10)
                    ->assertSee('test_worker')
                    ->screenshot('cj20-supervisor-created');
        });
    }

    /**
     * Journey: Restart Supervisor program via model method
     */
    public function test_21_restart_supervisor_program_via_model(): void
    {
        $program = SupervisorProgram::where('name', 'test_worker')->first();
        if (!$program) {
            $this->markTestSkipped('Supervisor program not found');
        }

        $job = $program->dispatchJob('supervisor_restart', [
            'name' => $program->name,
        ]);

        $this->assertTrue($this->waitForJobCompletion($job, 60), 'Supervisor restart failed: ' . ($job->error ?? ''));
    }

    /**
     * Journey: Create Supervisor program via Filament UI
     */
    public function test_22_create_supervisor_program_via_ui(): void
    {
        // Clean up
        SupervisorProgram::where('name', 'ui_test_worker')->delete();

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/app')
                    ->waitForText('Dashboard', 15);

            // Navigate to Workers
            $browser->clickLink('Workers')
                    ->waitForText('Workers', 10);

            // Click create
            $browser->click('a[href*="create"]')
                    ->waitForText('Create', 10)
                    ->screenshot('cj22-01-create-form');

            // Fill form
            $browser->type('input[id$="name"]', 'ui_test_worker')
                    ->type('input[id$="command"]', '/usr/bin/date >> /tmp/ui_worker.log');

            // Select server
            $browser->click('[id$="server_id"] button[type="button"]')
                    ->waitFor('[role="listbox"]', 5)
                    ->click('[role="option"]');

            $browser->pause(500)
                    ->screenshot('cj22-02-form-filled');

            // Submit
            $browser->scrollIntoView('button[type="submit"]')
                    ->click('button[type="submit"]')
                    ->waitForText('Created', 30)
                    ->screenshot('cj22-03-created');
        });

        // Wait for job
        $job = $this->waitForLatestJob('supervisor_create', 60);
        $this->assertNotNull($job, 'Supervisor create job did not complete');
        $this->assertEquals('completed', $job->status, 'Supervisor creation failed: ' . ($job->error ?? ''));
    }

    /**
     * Journey: Delete Supervisor program via model method (cleanup)
     */
    public function test_29_cleanup_supervisor_programs(): void
    {
        $programs = SupervisorProgram::whereIn('name', ['test_worker', 'ui_test_worker'])->get();

        foreach ($programs as $program) {
            $job = $program->dispatchJob('supervisor_delete', [
                'name' => $program->name,
            ]);

            $this->waitForJobCompletion($job, 30);
            $program->delete();
        }

        $this->assertTrue(true);
    }

    // =====================================================
    // VERIFICATION JOURNEYS
    // =====================================================

    /**
     * Journey: Verify all services are running
     */
    public function test_30_verify_all_services_running(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/app')
                    ->clickLink('Services')
                    ->waitForText('Services', 10)
                    ->screenshot('cj30-all-services');

            // Verify key services exist
            $browser->assertSee('nginx')
                    ->assertSee('active');
        });
    }

    /**
     * Journey: Verify both webapps are accessible
     */
    public function test_31_verify_webapps_in_list(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/app')
                    ->clickLink('Web Apps')
                    ->waitForText('Web Apps', 10)
                    ->screenshot('cj31-webapps-final');

            // Both apps should be visible
            $browser->assertSee('test1.test.example.com')
                    ->assertSee('test2.test.example.com')
                    ->assertSee('8.2')
                    ->assertSee('8.3');
        });
    }
}
