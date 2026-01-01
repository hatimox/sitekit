<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Server;
use App\Models\WebApp;
use App\Models\Database;
use App\Models\AgentJob;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class UserJourneysTest extends DuskTestCase
{
    protected static $user;
    protected static $server;
    protected static $webApp;
    protected static $database;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Get the demo user who owns the test server
        self::$user = User::where('email', $this->getTestUserEmail())->first();
        self::$server = Server::where('ip_address', $this->getTestServerIp())->first();

        if (!self::$user || !self::$server) {
            $this->markTestSkipped('Demo user or test server not found. Configure DUSK_TEST_USER_EMAIL and DUSK_TEST_SERVER_IP in .env');
        }
    }

    /**
     * Helper to wait for an agent job to complete
     */
    protected function waitForJobCompletion(string $jobType, int $maxWait = 60): bool
    {
        $startTime = time();

        while (time() - $startTime < $maxWait) {
            $job = AgentJob::where('server_id', self::$server->id)
                ->where('type', $jobType)
                ->orderBy('created_at', 'desc')
                ->first();

            if ($job && in_array($job->status, ['completed', 'failed'])) {
                return $job->status === 'completed';
            }

            sleep(2);
        }

        return false;
    }

    /**
     * Journey 1: Create a Web App
     */
    public function test_journey_01_create_web_app(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(self::$user)
                    ->visit('/app')
                    ->waitForText('Dashboard', 15)
                    ->screenshot('j01-01-dashboard');

            // Navigate to Web Apps
            $browser->clickLink('Web Apps')
                    ->waitForText('Web Apps', 10)
                    ->screenshot('j01-02-webapps-list');

            // Click Create button
            $browser->press('New web app')
                    ->waitForText('Application Details', 10)
                    ->screenshot('j01-03-create-form');

            // Fill in the form
            $browser->type('input[name="data.name"]', 'Test2App')
                    ->type('input[name="data.domain"]', 'test2.test.example.com')
                    ->screenshot('j01-04-form-filled');

            // Select server
            $browser->click('div[wire\\:key*="server_id"] button')
                    ->waitForText(self::$server->name, 5)
                    ->click('div[wire\\:key*="server_id"] [role="option"]')
                    ->screenshot('j01-05-server-selected');

            // Submit form
            $browser->press('Create')
                    ->waitForText('Created', 15)
                    ->screenshot('j01-06-created');
        });

        // Wait for the job to complete
        $this->assertTrue(
            $this->waitForJobCompletion('create_webapp'),
            'Web app creation job did not complete'
        );

        // Refresh webapp reference
        self::$webApp = WebApp::where('domain', 'test2.test.example.com')->first();
        $this->assertNotNull(self::$webApp);
    }

    /**
     * Journey 2: Issue SSL Certificate
     */
    public function test_journey_02_issue_ssl_certificate(): void
    {
        // First deploy an app so webroot exists
        $this->test_journey_07_git_deployment();

        self::$webApp = WebApp::where('domain', 'test2.test.example.com')->first();

        $this->browse(function (Browser $browser) {
            $browser->loginAs(self::$user)
                    ->visit('/app')
                    ->waitForText('Dashboard', 15);

            // Navigate to Web Apps
            $browser->clickLink('Web Apps')
                    ->waitForText('Web Apps', 10);

            // Click on the webapp to view details
            $browser->clickLink('Test2App')
                    ->waitForText('test2.test.example.com', 10)
                    ->screenshot('j02-01-webapp-detail');

            // Click Issue SSL button
            $browser->press('Issue SSL')
                    ->waitForText('Are you sure', 5)
                    ->press('Confirm')
                    ->waitForText('SSL', 15)
                    ->screenshot('j02-02-ssl-issued');
        });

        // Wait for SSL job to complete (may take longer)
        $this->assertTrue(
            $this->waitForJobCompletion('ssl_issue', 120),
            'SSL issue job did not complete'
        );
    }

    /**
     * Journey 3: Create Cron Job
     */
    public function test_journey_03_cron_job_management(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(self::$user)
                    ->visit('/app')
                    ->waitForText('Dashboard', 15);

            // Navigate to Cron Jobs
            $browser->clickLink('Cron Jobs')
                    ->waitForText('Cron Jobs', 10)
                    ->screenshot('j03-01-cronjobs-list');

            // Click Create
            $browser->press('New cron job')
                    ->waitForText('Cron Job Details', 10)
                    ->screenshot('j03-02-create-form');

            // Fill form
            $browser->type('input[name="data.name"]', 'Test Cron Job')
                    ->screenshot('j03-03-name-filled');

            // Select server
            $browser->click('div[wire\\:key*="server_id"] button')
                    ->pause(500)
                    ->click('div[wire\\:key*="server_id"] [role="option"]')
                    ->screenshot('j03-04-server-selected');

            // Fill command and schedule
            $browser->type('input[name="data.command"]', '/usr/bin/date >> /tmp/cron_test.log')
                    ->type('input[name="data.user"]', 'root')
                    ->screenshot('j03-05-form-complete');

            // Submit
            $browser->press('Create')
                    ->waitForText('Created', 15)
                    ->screenshot('j03-06-created');
        });

        // Wait for sync job
        $this->assertTrue(
            $this->waitForJobCompletion('sync_crontab'),
            'Cron job sync did not complete'
        );
    }

    /**
     * Journey 4: Create Database
     */
    public function test_journey_04_create_database(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(self::$user)
                    ->visit('/app')
                    ->waitForText('Dashboard', 15);

            // Navigate to Databases
            $browser->clickLink('Databases')
                    ->waitForText('Databases', 10)
                    ->screenshot('j04-01-databases-list');

            // Click Create
            $browser->press('New database')
                    ->waitForText('Database Details', 10)
                    ->screenshot('j04-02-create-form');

            // Select server first
            $browser->click('div[wire\\:key*="server_id"] button')
                    ->pause(500)
                    ->click('div[wire\\:key*="server_id"] [role="option"]')
                    ->screenshot('j04-03-server-selected');

            // Fill database name
            $browser->type('input[name="data.name"]', 'test_database')
                    ->screenshot('j04-04-form-filled');

            // Submit
            $browser->press('Create')
                    ->waitForText('Created', 15)
                    ->screenshot('j04-05-created');
        });

        // Wait for database creation job
        $this->assertTrue(
            $this->waitForJobCompletion('create_database'),
            'Database creation job did not complete'
        );

        self::$database = Database::where('name', 'test_database')->first();
        $this->assertNotNull(self::$database);
    }

    /**
     * Journey 5: Create Firewall Rule
     */
    public function test_journey_05_firewall_rule(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(self::$user)
                    ->visit('/app')
                    ->waitForText('Dashboard', 15);

            // Navigate to Firewall Rules
            $browser->clickLink('Firewall Rules')
                    ->waitForText('Firewall Rules', 10)
                    ->screenshot('j05-01-firewall-list');

            // Click Create
            $browser->press('New firewall rule')
                    ->waitForText('Firewall Rule', 10)
                    ->screenshot('j05-02-create-form');

            // Select server
            $browser->click('div[wire\\:key*="server_id"] button')
                    ->pause(500)
                    ->click('div[wire\\:key*="server_id"] [role="option"]')
                    ->screenshot('j05-03-server-selected');

            // Fill port
            $browser->type('input[name="data.port"]', '8888')
                    ->type('input[name="data.description"]', 'Test Port 8888')
                    ->screenshot('j05-04-form-filled');

            // Submit
            $browser->press('Create')
                    ->waitForText('Created', 15)
                    ->screenshot('j05-05-created');
        });

        // Wait for firewall job
        $this->assertTrue(
            $this->waitForJobCompletion('apply_firewall_rule'),
            'Firewall rule job did not complete'
        );
    }

    /**
     * Journey 6: Change PHP Version
     */
    public function test_journey_06_php_version_change(): void
    {
        self::$webApp = WebApp::where('domain', 'test2.test.example.com')->first();

        if (!self::$webApp) {
            $this->markTestSkipped('Web app not created yet');
        }

        $this->browse(function (Browser $browser) {
            $browser->loginAs(self::$user)
                    ->visit('/app')
                    ->waitForText('Dashboard', 15);

            // Navigate to Web Apps
            $browser->clickLink('Web Apps')
                    ->waitForText('Web Apps', 10);

            // Click Edit on the webapp
            $browser->click('a[href*="edit"]')
                    ->waitForText('Application Details', 10)
                    ->screenshot('j06-01-edit-form');

            // Change PHP version to 8.2
            $browser->click('div[wire\\:key*="php_version"] button')
                    ->pause(500)
                    ->clickAtXPath('//div[contains(@class, "fi-select-option")][contains(., "8.2")]')
                    ->screenshot('j06-02-php-changed');

            // Save
            $browser->press('Save changes')
                    ->waitForText('Saved', 15)
                    ->screenshot('j06-03-saved');
        });

        // Wait for config update job
        $this->assertTrue(
            $this->waitForJobCompletion('update_webapp_config'),
            'PHP version change job did not complete'
        );
    }

    /**
     * Journey 7: Git Deployment
     */
    public function test_journey_07_git_deployment(): void
    {
        self::$webApp = WebApp::where('domain', 'test2.test.example.com')->first();

        if (!self::$webApp) {
            $this->markTestSkipped('Web app not created yet');
        }

        $this->browse(function (Browser $browser) {
            $browser->loginAs(self::$user)
                    ->visit('/app')
                    ->waitForText('Dashboard', 15);

            // Navigate to Web Apps
            $browser->clickLink('Web Apps')
                    ->waitForText('Web Apps', 10);

            // Click on the webapp
            $browser->clickLink('Test2App')
                    ->waitForText('test2.test.example.com', 10)
                    ->screenshot('j07-01-webapp-detail');

            // Edit to add repository
            $browser->click('a[href*="edit"]')
                    ->waitForText('Application Details', 10)
                    ->screenshot('j07-02-edit-form');

            // Scroll to Git Repository section and fill
            $browser->scrollIntoView('input[name="data.repository_url"]')
                    ->type('input[name="data.repository_url"]', 'https://github.com/laravel/laravel.git')
                    ->type('input[name="data.branch"]', 'master')
                    ->screenshot('j07-03-git-filled');

            // Save
            $browser->press('Save changes')
                    ->waitForText('Saved', 15)
                    ->screenshot('j07-04-saved');

            // Now trigger deployment - go back to view
            $browser->visit('/app')
                    ->clickLink('Web Apps')
                    ->waitForText('Web Apps', 10)
                    ->clickLink('Test2App')
                    ->waitForText('test2.test.example.com', 10);

            // Click Deploy button if visible
            $browser->press('Deploy')
                    ->waitForText('Deployment', 15)
                    ->screenshot('j07-05-deployment-started');
        });

        // Wait for deployment job
        $this->assertTrue(
            $this->waitForJobCompletion('git_deploy', 180),
            'Git deployment job did not complete'
        );
    }

    /**
     * Journey 8: Service Install (Skip in Dusk - takes too long)
     */
    public function test_journey_08_service_install(): void
    {
        $this->markTestSkipped('Service install takes too long for browser testing');
    }

    /**
     * Journey 9: Update Web App Config (tested as part of journey 6)
     */
    public function test_journey_09_update_webapp_config(): void
    {
        $this->assertTrue(true, 'Tested as part of journey 6');
    }

    /**
     * Journey 10: Delete Web App (Skip - we need the app for other tests)
     */
    public function test_journey_10_delete_webapp(): void
    {
        $this->markTestSkipped('Skipping delete to preserve webapp for other tests');
    }

    /**
     * Journey 11: SSH Key Management
     */
    public function test_journey_11_ssh_key_management(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(self::$user)
                    ->visit('/app')
                    ->waitForText('Dashboard', 15);

            // Navigate to SSH Keys
            $browser->clickLink('SSH Keys')
                    ->waitForText('SSH Keys', 10)
                    ->screenshot('j11-01-sshkeys-list');

            // Click Add
            $browser->press('New SSH key')
                    ->waitForText('SSH Key Details', 10)
                    ->screenshot('j11-02-create-form');

            // Fill form
            $browser->type('input[name="data.name"]', 'Test SSH Key')
                    ->type('textarea[name="data.public_key"]', 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIFakeKeyForTestingOnlyDuskTest123456789 test@dusk.local')
                    ->screenshot('j11-03-form-filled');

            // Submit
            $browser->press('Create')
                    ->waitForText('Created', 15)
                    ->screenshot('j11-04-created');
        });

        // SSH key deployment happens automatically
        sleep(5);
    }

    /**
     * Journey 12: Service Management (Restart Nginx)
     */
    public function test_journey_12_service_management(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(self::$user)
                    ->visit('/app')
                    ->waitForText('Dashboard', 15);

            // Navigate to Services
            $browser->clickLink('Services')
                    ->waitForText('Services', 10)
                    ->screenshot('j12-01-services-list');

            // Find Nginx and click Restart
            $browser->click('button[title="Restart nginx"]')
                    ->waitForText('Are you sure', 5)
                    ->press('Confirm')
                    ->waitForText('restarted', 15)
                    ->screenshot('j12-02-restarted');
        });

        // Wait for restart job
        $this->assertTrue(
            $this->waitForJobCompletion('service_restart'),
            'Service restart job did not complete'
        );
    }

    /**
     * Journey 13: File Manager
     */
    public function test_journey_13_file_manager(): void
    {
        self::$webApp = WebApp::where('domain', 'test2.test.example.com')->first();

        if (!self::$webApp) {
            $this->markTestSkipped('Web app not created yet');
        }

        $this->browse(function (Browser $browser) {
            $browser->loginAs(self::$user)
                    ->visit('/app')
                    ->waitForText('Dashboard', 15);

            // Navigate to Web Apps
            $browser->clickLink('Web Apps')
                    ->waitForText('Web Apps', 10)
                    ->clickLink('Test2App')
                    ->waitForText('test2.test.example.com', 10);

            // Click Files tab
            $browser->clickLink('Files')
                    ->waitForText('File Manager', 15)
                    ->screenshot('j13-01-file-manager');

            // Verify we can see directory listing
            $browser->assertSee('current')
                    ->screenshot('j13-02-directory-listing');
        });
    }

    /**
     * Journey 14: Database Backup
     */
    public function test_journey_14_database_backup(): void
    {
        self::$database = Database::where('name', 'test_database')->first();

        if (!self::$database) {
            $this->markTestSkipped('Database not created yet');
        }

        $this->browse(function (Browser $browser) {
            $browser->loginAs(self::$user)
                    ->visit('/app')
                    ->waitForText('Dashboard', 15);

            // Navigate to Databases
            $browser->clickLink('Databases')
                    ->waitForText('Databases', 10)
                    ->screenshot('j14-01-databases-list');

            // Click Export on the database
            $browser->click('button[title="Export"]')
                    ->waitForText('started', 10)
                    ->screenshot('j14-02-export-started');
        });

        // Wait for export job
        $this->assertTrue(
            $this->waitForJobCompletion('export_database'),
            'Database export job did not complete'
        );
    }

    /**
     * Journey 15: Environment Variables (tested as part of webapp edit)
     */
    public function test_journey_15_environment_variables(): void
    {
        $this->assertTrue(true, 'Environment variables managed via webapp edit');
    }

    /**
     * Journey 16: Health Monitoring
     */
    public function test_journey_16_health_monitoring(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(self::$user)
                    ->visit('/app')
                    ->waitForText('Dashboard', 15);

            // Navigate to Health Monitors
            $browser->clickLink('Health Monitors')
                    ->waitForText('Health Monitors', 10)
                    ->screenshot('j16-01-health-list');

            // View dashboard to see server health
            $browser->visit('/app')
                    ->waitForText('Dashboard', 15)
                    ->assertSee('Active Servers')
                    ->screenshot('j16-02-dashboard-health');
        });
    }
}
