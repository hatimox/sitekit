<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Server;
use App\Models\WebApp;
use App\Models\Database;
use App\Models\DatabaseUser;
use App\Models\CronJob;
use App\Models\FirewallRule;
use App\Models\SshKey;
use App\Models\AgentJob;
use App\Models\SslCertificate;
use App\Services\ConfigGenerator\NginxConfigGenerator;
use App\Services\ConfigGenerator\PhpFpmConfigGenerator;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * End-to-end journey tests that:
 * 1. Create resources via model methods (what UI calls)
 * 2. Verify they appear correctly in the Filament UI
 * 3. Wait for agent jobs to complete
 * 4. Verify the result in UI
 */
class JourneyTest extends DuskTestCase
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

    protected function waitForJob(string $jobType, int $maxWait = 60): ?AgentJob
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

    /**
     * Journey 1: Create Web App
     */
    public function test_journey_01_create_webapp(): void
    {
        // Clean up any existing test apps and orphaned SSL certs
        $existingApps = WebApp::where('domain', 'test2.test.example.com')->get();
        foreach ($existingApps as $app) {
            SslCertificate::where('web_app_id', $app->id)->delete();
            $app->delete();
        }
        SslCertificate::where('domain', 'test2.test.example.com')->delete();

        // Create webapp via model (what UI does internally)
        // system_user defaults to 'sitekit' and root_path is computed as /home/sitekit/web/{domain}
        $webApp = WebApp::create([
            'team_id' => $this->server->team_id,
            'server_id' => $this->server->id,
            'name' => 'Test2App',
            'domain' => 'test2.test.example.com',
            'php_version' => '8.3',
            'status' => WebApp::STATUS_PENDING,
        ]);

        // Dispatch the creation job (what the observer does)
        $nginxGen = new NginxConfigGenerator();
        $phpGen = new PhpFpmConfigGenerator();

        AgentJob::create([
            'server_id' => $this->server->id,
            'team_id' => $this->server->team_id,
            'type' => 'create_webapp',
            'payload' => [
                'app_id' => $webApp->id,
                'domain' => $webApp->domain,
                'username' => $webApp->system_user,
                'php_version' => $webApp->php_version,
                'root_path' => $webApp->root_path,
                'nginx_config' => $nginxGen->generate($webApp),
                'fpm_config' => $phpGen->generate($webApp),
            ],
        ]);

        // Wait for job
        $job = $this->waitForJob('create_webapp', 90);
        $this->assertNotNull($job, 'Job did not complete');
        $this->assertEquals('completed', $job->status);

        // Verify in UI
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/app')
                    ->clickLink('Web Apps')
                    ->waitForText('Web Apps', 10)
                    ->assertSee('Test2App')
                    ->assertSee('test2.test.example.com')
                    ->screenshot('j01-webapp-in-list');
        });

        $webApp->update(['status' => WebApp::STATUS_ACTIVE]);
    }

    /**
     * Journey 2: SSL Certificate (requires webapp + deployment first)
     * This test is slow due to git clone + certbot, so we skip if already done
     */
    public function test_journey_02_ssl_certificate(): void
    {
        $webApp = WebApp::where('domain', 'test2.test.example.com')->first();
        if (!$webApp) {
            $this->markTestSkipped('WebApp not found - run journey 01 first');
        }

        // Check if SSL already issued
        $existingCert = SslCertificate::where('web_app_id', $webApp->id)->first();
        if ($existingCert && $existingCert->status === SslCertificate::STATUS_ACTIVE) {
            // Verify in UI even if already active
            $this->browse(function (Browser $browser) {
                $browser->loginAs($this->user)
                        ->visit('/app')
                        ->clickLink('Web Apps')
                        ->waitForText('Web Apps', 10)
                        ->assertSee('Test2App')
                        ->screenshot('j02-ssl-already-active');
            });
            $this->assertTrue(true, 'SSL already issued');
            return;
        }

        // Check if there's already a recent successful deploy for this webapp
        $existingDeploy = AgentJob::where('server_id', $this->server->id)
            ->where('type', 'git_deploy')
            ->where('status', 'completed')
            ->whereJsonContains('payload->app_path', $webApp->root_path)
            ->where('created_at', '>', now()->subHours(24))
            ->first();

        if (!$existingDeploy) {
            // Deploy to ensure webroot exists
            $deployJob = AgentJob::create([
                'server_id' => $this->server->id,
                'team_id' => $this->server->team_id,
                'type' => 'git_deploy',
                'payload' => [
                    'deployment_id' => 'ssl-deploy-' . uniqid(),
                    'app_path' => $webApp->root_path,
                    'username' => $webApp->system_user,
                    'repository' => 'https://github.com/laravel/laravel.git',
                    'branch' => 'master',
                    'commit_hash' => 'ssl' . date('His'),
                    'php_version' => $webApp->php_version,
                ],
            ]);

            // Wait for THIS specific job (5 minutes for git clone)
            $startTime = time();
            while (time() - $startTime < 300) {
                $deployJob->refresh();
                if (in_array($deployJob->status, ['completed', 'failed'])) {
                    break;
                }
                sleep(3);
            }
            $this->assertEquals('completed', $deployJob->status, 'Deploy failed: ' . ($deployJob->error ?? ''));
        }

        // Now issue SSL
        $cert = SslCertificate::firstOrCreate(
            ['web_app_id' => $webApp->id],
            ['domain' => $webApp->domain, 'status' => SslCertificate::STATUS_PENDING]
        );

        if ($cert->status !== SslCertificate::STATUS_ACTIVE) {
            $sslJob = $cert->dispatchIssue();

            // Wait for THIS specific job
            $startTime = time();
            while (time() - $startTime < 120) {
                $sslJob->refresh();
                if (in_array($sslJob->status, ['completed', 'failed'])) {
                    break;
                }
                sleep(3);
            }
            $this->assertEquals('completed', $sslJob->status, 'SSL issue failed: ' . $sslJob->error);
        }

        // Verify in UI
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/app')
                    ->clickLink('Web Apps')
                    ->waitForText('Web Apps', 10)
                    ->clickLink('Test2App')
                    ->waitForText('test2.test.example.com', 10)
                    ->screenshot('j02-webapp-with-ssl');
        });
    }

    /**
     * Journey 3: Cron Job
     */
    public function test_journey_03_cron_job(): void
    {
        $cronJob = CronJob::create([
            'server_id' => $this->server->id,
            'team_id' => $this->server->team_id,
            'name' => 'Dusk Test Cron',
            'command' => '/usr/bin/date >> /tmp/dusk_cron.log',
            'schedule' => '*/5 * * * *',
            'user' => 'root',
            'is_active' => true,
        ]);

        $cronJob->syncToServer();

        $job = $this->waitForJob('sync_crontab');
        $this->assertNotNull($job, 'Cron sync job did not complete');
        $this->assertEquals('completed', $job->status);

        // Verify in UI
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/app')
                    ->clickLink('Cron Jobs')
                    ->waitForText('Cron Jobs', 10)
                    ->assertSee('Dusk Test Cron')
                    ->screenshot('j03-cron-in-list');
        });
    }

    /**
     * Journey 4: Database with User
     */
    public function test_journey_04_database(): void
    {
        // Clean up existing
        DatabaseUser::where('username', 'dusk_user')->delete();
        Database::where('name', 'dusk_db')->delete();

        $database = Database::create([
            'server_id' => $this->server->id,
            'team_id' => $this->server->team_id,
            'name' => 'dusk_db',
            'type' => Database::TYPE_MARIADB,
            'status' => Database::STATUS_PENDING,
        ]);

        // Create database user (like UI does by default)
        $dbUser = DatabaseUser::create([
            'database_id' => $database->id,
            'server_id' => $this->server->id,
            'username' => 'dusk_user',
            'password' => 'DuskTestPass123!',
            'can_remote' => false,
        ]);

        // Dispatch job with user credentials (like UI does)
        $database->dispatchJob('create_database', [
            'database_id' => $database->id,
            'database_name' => $database->name,
            'database_type' => $database->type,
            'username' => $dbUser->username,
            'password' => $dbUser->password,
            'host' => 'localhost',
        ]);

        $job = $this->waitForJob('create_database');
        $this->assertNotNull($job, 'Database job did not complete');
        $this->assertEquals('completed', $job->status);

        $database->update(['status' => Database::STATUS_ACTIVE]);

        // Verify database in UI
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/app')
                    ->clickLink('Databases')
                    ->waitForText('Databases', 10)
                    ->assertSee('dusk_db')
                    ->screenshot('j04-database-in-list');
        });

        // Verify user was created in database
        $this->assertDatabaseHas('database_users', [
            'database_id' => $database->id,
            'username' => 'dusk_user',
        ]);
    }

    /**
     * Journey 5: Firewall Rule
     */
    public function test_journey_05_firewall_rule(): void
    {
        $rule = FirewallRule::create([
            'server_id' => $this->server->id,
            'team_id' => $this->server->team_id,
            'port' => '7777',
            'protocol' => 'tcp',
            'action' => 'allow',
            'direction' => 'in',
            'from_ip' => 'any',
            'description' => 'Dusk Test Port',
            'is_active' => true,
        ]);

        $rule->dispatchApply();

        $job = $this->waitForJob('apply_firewall_rule');
        $this->assertNotNull($job, 'Firewall job did not complete');
        $this->assertEquals('completed', $job->status);

        // Verify in UI
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/app')
                    ->clickLink('Firewall Rules')
                    ->waitForText('Firewall Rules', 10)
                    ->assertSee('7777')
                    ->assertSee('Dusk Test Port')
                    ->screenshot('j05-firewall-in-list');
        });
    }

    /**
     * Journey 6: PHP Version Change
     */
    public function test_journey_06_php_version_change(): void
    {
        $webApp = WebApp::where('domain', 'test2.test.example.com')->first();
        if (!$webApp) {
            $this->markTestSkipped('WebApp not found');
        }

        $oldVersion = $webApp->php_version;
        $newVersion = $oldVersion === '8.3' ? '8.2' : '8.3';

        $webApp->update(['php_version' => $newVersion]);

        $nginxGen = new NginxConfigGenerator();
        $phpGen = new PhpFpmConfigGenerator();

        AgentJob::create([
            'server_id' => $this->server->id,
            'team_id' => $this->server->team_id,
            'type' => 'update_webapp_config',
            'payload' => [
                'app_id' => $webApp->id,
                'domain' => $webApp->domain,
                'username' => $webApp->system_user,
                'php_version' => $newVersion,
                'old_php_version' => $oldVersion,
                'nginx_config' => $nginxGen->generate($webApp),
                'fpm_config' => $phpGen->generate($webApp),
            ],
        ]);

        $job = $this->waitForJob('update_webapp_config');
        $this->assertNotNull($job, 'Config update job did not complete');
        $this->assertEquals('completed', $job->status);

        // Verify in UI
        $this->browse(function (Browser $browser) use ($newVersion) {
            $browser->loginAs($this->user)
                    ->visit('/app')
                    ->clickLink('Web Apps')
                    ->waitForText('Web Apps', 10)
                    ->clickLink('Test2App')
                    ->waitForText('test2.test.example.com', 10)
                    ->assertSee($newVersion)
                    ->screenshot('j06-php-version-changed');
        });
    }

    /**
     * Journey 7: Git Deployment
     */
    public function test_journey_07_git_deployment(): void
    {
        $webApp = WebApp::where('domain', 'test2.test.example.com')->first();
        if (!$webApp) {
            $this->markTestSkipped('WebApp not found');
        }

        $deploymentId = 'dusk-deploy-' . uniqid();

        AgentJob::create([
            'server_id' => $this->server->id,
            'team_id' => $this->server->team_id,
            'type' => 'git_deploy',
            'payload' => [
                'deployment_id' => $deploymentId,
                'app_path' => $webApp->root_path,
                'username' => $webApp->system_user,
                'repository' => 'https://github.com/laravel/laravel.git',
                'branch' => 'master',
                'commit_hash' => 'deploy' . date('His'),
                'php_version' => $webApp->php_version,
            ],
        ]);

        $job = $this->waitForJob('git_deploy', 180);
        $this->assertNotNull($job, 'Deploy job did not complete');
        $this->assertEquals('completed', $job->status);

        // Verify in UI - check Agent Jobs
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/app')
                    ->clickLink('Agent Jobs')
                    ->waitForText('Agent Jobs', 10)
                    ->assertSee('git_deploy')
                    ->assertSee('completed')
                    ->screenshot('j07-deployment-completed');
        });
    }

    /**
     * Journey 11: SSH Key
     */
    public function test_journey_11_ssh_key(): void
    {
        $sshKey = SshKey::create([
            'team_id' => $this->server->team_id,
            'user_id' => $this->user->id,
            'name' => 'Dusk Test Key',
            'public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIDuskKey1234567890DuskTest dusk@test.local',
        ]);

        AgentJob::create([
            'server_id' => $this->server->id,
            'team_id' => $this->server->team_id,
            'type' => 'ssh_key_add',
            'payload' => [
                'key_id' => $sshKey->id,
                'public_key' => $sshKey->public_key,
                'username' => 'root',
            ],
        ]);

        $job = $this->waitForJob('ssh_key_add');
        $this->assertNotNull($job, 'SSH key job did not complete');
        $this->assertEquals('completed', $job->status);

        // Verify in UI
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/app')
                    ->clickLink('SSH Keys')
                    ->waitForText('SSH Keys', 10)
                    ->assertSee('Dusk Test Key')
                    ->screenshot('j11-sshkey-in-list');
        });
    }

    /**
     * Journey 12: Service Management (Restart Nginx)
     */
    public function test_journey_12_service_restart(): void
    {
        AgentJob::create([
            'server_id' => $this->server->id,
            'team_id' => $this->server->team_id,
            'type' => 'service_restart',
            'payload' => [
                'service_type' => 'nginx',
                'version' => '',
            ],
        ]);

        $job = $this->waitForJob('service_restart');
        $this->assertNotNull($job, 'Service restart job did not complete');
        $this->assertEquals('completed', $job->status);

        // Verify in UI
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/app')
                    ->clickLink('Agent Jobs')
                    ->waitForText('Agent Jobs', 10)
                    ->assertSee('service_restart')
                    ->assertSee('completed')
                    ->screenshot('j12-service-restart-completed');
        });
    }

    /**
     * Journey 13: File Manager (List Directory)
     */
    public function test_journey_13_file_manager(): void
    {
        AgentJob::create([
            'server_id' => $this->server->id,
            'team_id' => $this->server->team_id,
            'type' => 'list_directory',
            'payload' => [
                'path' => '/var/www',
                'base_path' => '/var/www',
            ],
        ]);

        $job = $this->waitForJob('list_directory');
        $this->assertNotNull($job, 'List directory job did not complete');
        $this->assertEquals('completed', $job->status);
    }

    /**
     * Journey 14: Database Backup
     */
    public function test_journey_14_database_backup(): void
    {
        $database = Database::where('name', 'dusk_db')->first();
        if (!$database) {
            $this->markTestSkipped('Database not found');
        }

        AgentJob::create([
            'server_id' => $this->server->id,
            'team_id' => $this->server->team_id,
            'type' => 'database_backup',
            'payload' => [
                'backup_id' => 'dusk-backup-' . uniqid(),
                'database_id' => $database->id,
                'database_name' => $database->name,
                'database_type' => $database->type,
                'filename' => 'dusk_backup_' . date('Y-m-d_H-i-s') . '.sql.gz',
            ],
        ]);

        $job = $this->waitForJob('database_backup');
        $this->assertNotNull($job, 'Database backup job did not complete');
        $this->assertEquals('completed', $job->status);
    }

    /**
     * Journey 16: Health Monitoring
     */
    public function test_journey_16_health_monitoring(): void
    {
        AgentJob::create([
            'server_id' => $this->server->id,
            'team_id' => $this->server->team_id,
            'type' => 'check_services',
            'payload' => [
                'services' => ['nginx', 'mariadb', 'redis'],
            ],
        ]);

        $job = $this->waitForJob('check_services');
        $this->assertNotNull($job, 'Health check job did not complete');
        $this->assertEquals('completed', $job->status);

        // Verify in UI - check dashboard shows health info
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/app')
                    ->waitForText('Dashboard', 15)
                    ->assertSee('Dashboard')
                    ->screenshot('j16-dashboard-health');
        });
    }
}
