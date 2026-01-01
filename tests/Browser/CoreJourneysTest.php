<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Server;
use App\Models\WebApp;
use App\Models\Database;
use App\Models\AgentJob;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class CoreJourneysTest extends DuskTestCase
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

    protected function waitForLatestJob(string $jobType, int $maxWait = 60): bool
    {
        $startTime = time();
        while (time() - $startTime < $maxWait) {
            $job = AgentJob::where('server_id', $this->server->id)
                ->where('type', $jobType)
                ->orderBy('created_at', 'desc')
                ->first();

            if ($job && in_array($job->status, ['completed', 'failed'])) {
                echo "\nJob {$job->type}: {$job->status}\n";
                return $job->status === 'completed';
            }
            sleep(2);
        }
        return false;
    }

    /**
     * Test creating a web app via Filament UI
     */
    public function test_create_webapp_via_ui(): void
    {
        // Delete any existing test app first
        WebApp::where('domain', 'test2.test.example.com')->delete();

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/app')
                    ->waitForText('Dashboard', 15);

            // Navigate to Web Apps
            $browser->clickLink('Web Apps')
                    ->waitForText('Web Apps', 10);

            // Create new web app
            $browser->click('a[href*="create"]')
                    ->waitForText('Create', 10)
                    ->screenshot('webapp-01-create-form');

            // Fill in name
            $browser->waitFor('input[id$="name"]', 10)
                    ->type('input[id$="name"]', 'Test2App');

            // Fill in domain
            $browser->type('input[id$="domain"]', 'test2.test.example.com');

            // Select server - click the select button
            $browser->click('[id$="server_id"] button[type="button"]')
                    ->waitFor('[role="listbox"]', 5)
                    ->click('[role="option"]')
                    ->screenshot('webapp-02-form-filled');

            // Submit
            $browser->scrollIntoView('button[type="submit"]')
                    ->click('button[type="submit"]')
                    ->waitForText('Created', 30)
                    ->screenshot('webapp-03-created');
        });

        // Wait for job completion
        $this->assertTrue($this->waitForLatestJob('create_webapp', 90));

        // Verify webapp exists and is active
        $webApp = WebApp::where('domain', 'test2.test.example.com')->first();
        $this->assertNotNull($webApp);
    }

    /**
     * Test creating a firewall rule via UI
     */
    public function test_create_firewall_rule_via_ui(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/app')
                    ->waitForText('Dashboard', 15);

            // Navigate to Firewall Rules
            $browser->clickLink('Firewall Rules')
                    ->waitForText('Firewall Rules', 10);

            // Create new rule
            $browser->click('a[href*="create"]')
                    ->waitForText('Create Firewall Rule', 10)
                    ->pause(1000)
                    ->screenshot('firewall-01-create-form');

            // Click on the Server select field using JavaScript
            $browser->script("
                // Find the Server label and click its sibling button
                const labels = document.querySelectorAll('label');
                for (const label of labels) {
                    if (label.textContent.includes('Server')) {
                        const container = label.closest('.fi-fo-field-wrp');
                        if (container) {
                            const button = container.querySelector('button');
                            if (button) button.click();
                            break;
                        }
                    }
                }
            ");
            $browser->pause(500)
                    ->screenshot('firewall-02-server-dropdown');

            // Click on the server in the dropdown
            $browser->waitFor('[role="listbox"]', 5)
                    ->click('[role="listbox"] [role="option"]')
                    ->pause(500)
                    ->screenshot('firewall-03-server-selected');

            // Fill port - using keys() with type() for Livewire compatibility
            $browser->keys('input[placeholder*="22"]', '7777')
                    ->pause(300)
                    ->screenshot('firewall-04-port-filled');

            // Fill description
            $browser->keys('input[placeholder*="SSH"]', 'Test Port via Dusk')
                    ->pause(300)
                    ->screenshot('firewall-05-form-filled');

            // Submit
            $browser->press('Create')
                    ->waitForText('Created', 30)
                    ->screenshot('firewall-06-created');
        });

        // Wait for job completion
        $this->assertTrue($this->waitForLatestJob('apply_firewall_rule'));
    }

    /**
     * Test creating a database via UI
     */
    public function test_create_database_via_ui(): void
    {
        // Delete any existing test database
        Database::where('name', 'dusk_test_db')->delete();

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/app')
                    ->waitForText('Dashboard', 15);

            // Navigate to Databases
            $browser->clickLink('Databases')
                    ->waitForText('Databases', 10);

            // Create new database
            $browser->click('a[href*="create"]')
                    ->waitForText('Create', 10)
                    ->screenshot('database-01-create-form');

            // Select server
            $browser->click('[id$="server_id"] button[type="button"]')
                    ->waitFor('[role="listbox"]', 5)
                    ->click('[role="option"]');

            // Fill database name
            $browser->type('input[id$="name"]', 'dusk_test_db')
                    ->screenshot('database-02-form-filled');

            // Submit
            $browser->click('button[type="submit"]')
                    ->waitForText('Created', 30)
                    ->screenshot('database-03-created');
        });

        // Wait for job completion
        $this->assertTrue($this->waitForLatestJob('create_database'));

        // Verify database exists
        $db = Database::where('name', 'dusk_test_db')->first();
        $this->assertNotNull($db);
    }

    /**
     * Test creating a cron job via UI
     */
    public function test_create_cron_job_via_ui(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/app')
                    ->waitForText('Dashboard', 15);

            // Navigate to Cron Jobs
            $browser->clickLink('Cron Jobs')
                    ->waitForText('Cron Jobs', 10);

            // Create new cron job
            $browser->click('a[href*="create"]')
                    ->waitForText('Create', 10)
                    ->screenshot('cronjob-01-create-form');

            // Fill name
            $browser->type('input[id$="name"]', 'Dusk Test Cron');

            // Select server
            $browser->click('[id$="server_id"] button[type="button"]')
                    ->waitFor('[role="listbox"]', 5)
                    ->click('[role="option"]');

            // Fill command
            $browser->type('input[id$="command"]', '/usr/bin/date >> /tmp/dusk_cron.log')
                    ->screenshot('cronjob-02-form-filled');

            // Submit
            $browser->click('button[type="submit"]')
                    ->waitForText('Created', 30)
                    ->screenshot('cronjob-03-created');
        });

        // Wait for job completion
        $this->assertTrue($this->waitForLatestJob('sync_crontab'));
    }

    /**
     * Test adding an SSH key via UI
     */
    public function test_add_ssh_key_via_ui(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/app')
                    ->waitForText('Dashboard', 15);

            // Navigate to SSH Keys
            $browser->clickLink('SSH Keys')
                    ->waitForText('SSH Keys', 10);

            // Create new SSH key
            $browser->click('a[href*="create"]')
                    ->waitForText('Create', 10)
                    ->screenshot('sshkey-01-create-form');

            // Fill form
            $browser->type('input[id$="name"]', 'Dusk Test Key')
                    ->type('textarea[id$="public_key"]', 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIDuskTestKey12345678901234567890dusk dusk@test.local')
                    ->screenshot('sshkey-02-form-filled');

            // Submit
            $browser->click('button[type="submit"]')
                    ->waitForText('Created', 30)
                    ->screenshot('sshkey-03-created');
        });

        // SSH key sync happens on creation
        sleep(3);
    }

    /**
     * Test viewing server services
     */
    public function test_view_services(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/app')
                    ->waitForText('Dashboard', 15);

            // Navigate to Services
            $browser->clickLink('Services')
                    ->waitForText('Services', 10)
                    ->screenshot('services-01-list');

            // Verify services are listed
            $browser->assertSee('nginx')
                    ->screenshot('services-02-nginx-visible');
        });
    }

    /**
     * Test issuing SSL certificate via UI
     */
    public function test_issue_ssl_certificate_via_ui(): void
    {
        // Ensure webapp exists
        $webApp = WebApp::where('domain', 'test2.test.example.com')->first();
        if (!$webApp) {
            $this->test_create_webapp_via_ui();
            $webApp = WebApp::where('domain', 'test2.test.example.com')->first();
        }

        // First deploy so webroot exists
        $webApp->update(['repository_url' => 'https://github.com/laravel/laravel.git', 'branch' => 'master']);

        // Trigger deployment via model (UI deployment is complex)
        $deploymentId = 'dusk-deploy-' . uniqid();
        AgentJob::create([
            'server_id' => $this->server->id,
            'team_id' => $this->server->team_id,
            'type' => 'git_deploy',
            'payload' => [
                'deployment_id' => $deploymentId,
                'app_path' => $webApp->root_path,
                'username' => $webApp->system_user,
                'repository' => $webApp->repository_url,
                'branch' => $webApp->branch,
                'commit_hash' => 'dusk' . date('His'),
                'php_version' => $webApp->php_version,
            ],
        ]);

        // Wait for deployment
        $this->assertTrue($this->waitForLatestJob('git_deploy', 180));

        // Now issue SSL via UI
        $this->browse(function (Browser $browser) use ($webApp) {
            $browser->loginAs($this->user)
                    ->visit('/app')
                    ->waitForText('Dashboard', 15);

            // Navigate to Web Apps
            $browser->clickLink('Web Apps')
                    ->waitForText('Web Apps', 10);

            // Click on webapp
            $browser->clickLink('Test2App')
                    ->waitForText('test2.test.example.com', 10)
                    ->screenshot('ssl-01-webapp-view');

            // Click Issue SSL button
            $browser->press('Issue SSL')
                    ->waitFor('.fi-modal', 5)
                    ->screenshot('ssl-02-confirm-modal');

            // Confirm
            $browser->press('Confirm')
                    ->screenshot('ssl-03-issued');
        });

        // Wait for SSL job
        $this->assertTrue($this->waitForLatestJob('ssl_issue', 120));

        // Verify SSL status updated
        $webApp->refresh();
        $this->assertNotEquals('none', $webApp->ssl_status);
    }
}
